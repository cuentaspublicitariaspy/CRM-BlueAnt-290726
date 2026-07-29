<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/../agentes/Services/Logger.php';
require_once __DIR__ . '/../agentes/Services/RateLimiter.php';
require_once __DIR__ . '/../agentes/Middleware/Cors.php';
require_once __DIR__ . '/../agentes/Helpers/KnowledgeBaseHelper.php';
require_once __DIR__ . '/../agentes/Helpers/ResponseFormatter.php';
require_once __DIR__ . '/../agentes/Services/TfIdfSearch.php';
require_once __DIR__ . '/../agentes/Services/OpenAi.php';

AgentCors::handlePreflight();
AgentLogger::init();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$agentId = $input['agent_id'] ?? '';
$message = trim($input['message'] ?? '');
$sessionToken = $input['session'] ?? '';
$ipHash = AgentRateLimiter::getIpHash();

$rateLimiter = new AgentRateLimiter($pdo);
$openAi = new AgentOpenAi($pdo);

function cSanitize(string $s): string {
    $s = strip_tags($s); $s = preg_replace('/\s+/', ' ', $s); $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    return trim(mb_substr($s, 0, 1000));
}
function cSpam(PDO $db, string $msg, string $ip, ?int $sid): array {
    if ($sid) {
        $st = $db->prepare("SELECT content FROM chat_messages WHERE session_id = ? AND role = 'user' ORDER BY id DESC LIMIT 3");
        $st->execute([$sid]); $r = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($r) >= 3 && $r === array_fill(0, 3, $msg)) return ['is_spam'=>true,'reason'=>'repetido'];
    }
    if (mb_strlen($msg) > 0) {
        $n = mb_strlen(preg_replace('/[^\w\s@.,!?¿¡:;\-\+\'\"áéíóúüñ]/u','',$msg));
        if (($n/mb_strlen($msg)) < 0.4) return ['is_spam'=>true,'reason'=>'formato'];
    }
    if (preg_match_all('/https?:\/\/[^\s]+/i',$msg) > 3) return ['is_spam'=>true,'reason'=>'links'];
    $st = $db->prepare("SELECT COUNT(*) FROM chat_messages m JOIN chat_sessions s ON m.session_id=s.id WHERE s.ip_hash=? AND m.role='user' AND m.created_at>DATE_SUB(NOW(),INTERVAL 5 SECOND)");
    $st->execute([$ip]); if ((int)$st->fetchColumn() > 5) return ['is_spam'=>true,'reason'=>'velocidad'];
    return ['is_spam'=>false,'reason'=>''];
}
function cLeadProfile(PDO $db, string $aid, int $sid): array {
    $st = $db->prepare("SELECT * FROM lead_profiles WHERE session_id=?");
    $st->execute([$sid]); $p = $st->fetch();
    if (!$p) {
        $db->prepare("INSERT INTO lead_profiles (agent_id,session_id) VALUES (?,?)")->execute([$aid,$sid]);
        $id = (int)$db->lastInsertId();
        $st = $db->prepare("SELECT * FROM lead_profiles WHERE id=?"); $st->execute([$id]); $p = $st->fetch();
    }
    return $p ?: [];
}
function cBizEvent(PDO $db, string $aid, ?int $sid, string $type): void {
    $allowed = ['lead_created','lead_updated','phone_captured','email_captured','pricing_requested','whatsapp_clicked','human_requested','proposal_requested','high_intent_detected','spam_detected','agent_conversation_started','agent_conversation_summary_updated'];
    if (!in_array($type, $allowed, true)) return;
    $db->prepare("INSERT INTO business_events (agent_id,session_id,event_type) VALUES (?,?,?)")->execute([$aid,$sid,$type]);
}
function cMeta(PDO $db, int $mid, int $sid, array $m): void {
    $db->prepare("INSERT INTO chat_message_metadata (message_id,session_id,intent,topic,lead_score_delta,next_action,extracted_fields,full_metadata) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$mid,$sid,$m['intent']??null,$m['topic']??null,$m['lead_score_delta']??0,$m['next_action']??null,json_encode($m['extracted_data']??[]),json_encode($m)]);
}
function cUpdateLead(PDO $db, int $pid, array $res, string $aid): void {
    $meta = $res['metadata']; $ext = $meta['extracted_data']??[]; $delta = (int)($meta['lead_score_delta']??0); $stage = $meta['lead_stage']??'new'; $su = $meta['conversation_summary_update']??'';
    $st = $db->prepare("SELECT * FROM lead_profiles WHERE id=?"); $st->execute([$pid]); $p = $st->fetch(); if (!$p) return;
    $ns = max(0, min(1000, (int)$p['lead_score'] + $delta));
    $up = []; $pa = []; $fields = ['name','email','phone','company','website','country','city','service_interest','main_problem','estimated_budget','urgency'];
    foreach ($fields as $f) {
        if (!isset($ext[$f]) || $ext[$f]===null || $ext[$f]==='') continue;
        if ($p[$f]!==null && $p[$f]!=='' && $p[$f]!=='0.00') continue;
        $v = $ext[$f];
        if ($f==='email' && !filter_var($v,FILTER_VALIDATE_EMAIL)) continue;
        if ($f==='phone') { $d = preg_replace('/[^\d]/','',$v); if (mb_strlen($d)<7||mb_strlen($d)>15) continue; }
        if ($f==='estimated_budget') { $n = filter_var($v,FILTER_VALIDATE_FLOAT); if ($n===false||$n<0) continue; $v=(string)$n; }
        $up[] = "$f=?"; $pa[] = mb_substr(trim($v),0,500);
    }
    if ((int)$p['lead_score'] !== $ns) { $up[]="lead_score=?"; $pa[]=$ns; }
    if (in_array($stage, ['new','cold','warm','hot','qualified','closed'], true) && $p['lead_stage']!==$stage) { $up[]="lead_stage=?"; $pa[]=$stage; }
    if ($su) { $c=$p['conversation_summary']??''; $up[]="conversation_summary=?"; $pa[]=$c?mb_substr($c.' '.$su,0,2000):mb_substr($su,0,2000); }
    if (empty($up)) return; $pa[]=$pid;
    $db->prepare("UPDATE lead_profiles SET ".implode(',',$up)." WHERE id=?")->execute($pa);
}
function cSync(PDO $db, int $pid, int $sid): void {
    $st = $db->prepare("SELECT * FROM lead_profiles WHERE id=?"); $st->execute([$pid]); $p = $st->fetch();
    $name = trim($p['name']??''); $email = trim($p['email']??''); $phone = trim($p['phone']??'');
    if ($name==='' || ($email==='' && $phone==='')) return;
    $st = $db->prepare("SELECT domain FROM chat_sessions WHERE id=?"); $st->execute([$sid]); $domain = $st->fetchColumn()?:'';
    if ($email) { $st = $db->prepare("SELECT id FROM prospects WHERE email=? LIMIT 1"); $st->execute([$email]); }
    else { $st = $db->prepare("SELECT id FROM prospects WHERE whatsapp=? LIMIT 1"); $st->execute([$phone]); }
    $exist = $st->fetchColumn(); $d = ['user_id'=>1,'name'=>$name,'email'=>$email?:'','whatsapp'=>$phone?:'','domain'=>$domain];
    try { $o = $db->prepare("SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1"); $o->execute(); $oid=$o->fetchColumn(); if($oid)$d['user_id']=(int)$oid; } catch(Exception $e){}
    if ($exist) $db->prepare("UPDATE prospects SET name=?,email=?,whatsapp=?,domain=? WHERE id=?")->execute([$d['name'],$d['email'],$d['whatsapp'],$d['domain'],$exist]);
    else $db->prepare("INSERT INTO prospects (user_id,name,email,whatsapp,domain) VALUES (?,?,?,?,?)")->execute([$d['user_id'],$d['name'],$d['email'],$d['whatsapp'],$d['domain']]);
}
function cEvents(PDO $db, string $aid, int $sid, array $meta, bool $spam): void {
    $e = [];
    if ($spam) $e[]='spam_detected';
    if (!empty($meta['extracted_data']['phone'])) $e[]='phone_captured';
    if (!empty($meta['extracted_data']['email'])) $e[]='email_captured';
    if (($meta['intent']??'')==='pricing_question') $e[]='pricing_requested';
    if (($meta['intent']??'')==='human_request') $e[]='human_requested';
    if (($meta['lead_score_delta']??0)>=30) $e[]='high_intent_detected';
    if (($meta['next_action']??'')==='create_lead') $e[]='lead_created';
    if (($meta['next_action']??'')==='update_lead') $e[]='lead_updated';
    if (!empty($meta['conversation_summary_update'])) $e[]='agent_conversation_summary_updated';
    foreach ($e as $ev) cBizEvent($db, $aid, $sid, $ev);
}
function cLeadCtx(?array $lp): ?array {
    if (!$lp||empty($lp['id'])) return null;
    return ['name'=>$lp['name']??null,'service_interest'=>$lp['service_interest']??null,'main_problem'=>$lp['main_problem']??null,'urgency'=>$lp['urgency']??null,'lead_score'=>(int)($lp['lead_score']??0),'lead_stage'=>$lp['lead_stage']??'new','conversation_summary'=>$lp['conversation_summary']??null];
}

try {
    if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Metodo no permitido']); exit; }
    if (!preg_match('/^ag_[a-f0-9]{28}$/', $agentId)) { http_response_code(400); echo json_encode(['error'=>'ID de agente invalido']); exit; }
    if ($message === '' || mb_strlen($message) > 1000) { http_response_code(400); echo json_encode(['error'=>'Mensaje invalido']); exit; }
    $message = cSanitize($message);
    if ($message === '') { http_response_code(400); echo json_encode(['error'=>'Mensaje vacio']); exit; }

    try { AgentCors::validatePublicEndpoint($agentId, $pdo); }
    catch (RuntimeException $e) { http_response_code(403); echo json_encode(['error'=>$e->getMessage()]); exit; }

    try { $rateLimiter->check('chat_hour:'.$ipHash, 30, 3600, 'chat_hourly'); $rateLimiter->check('chat_min:'.$ipHash, 5, 60, 'chat_minute'); }
    catch (RuntimeException $e) { http_response_code(429); echo json_encode(['error'=>$e->getMessage()]); exit; }

    $st = $pdo->prepare("SELECT * FROM agents WHERE id=? AND is_active=1 LIMIT 1");
    $st->execute([$agentId]); $agent = $st->fetch();
    if (!$agent) { http_response_code(404); echo json_encode(['error'=>'Agente no encontrado o inactivo']); exit; }

    $sessionId = null; $msgCount = 0;
    if ($sessionToken !== '') {
        if (!preg_match('/^[a-f0-9]{64}$/', $sessionToken)) $sessionToken = '';
        else {
            $st = $pdo->prepare("SELECT id,message_count FROM chat_sessions WHERE session_token=? AND agent_id=? LIMIT 1");
            $st->execute([$sessionToken, $agentId]); $s = $st->fetch();
            if ($s) { $sessionId=(int)$s['id']; $msgCount=(int)$s['message_count']; if ($msgCount>=(int)$agent['max_messages_per_session']) { http_response_code(429); echo json_encode(['error'=>'Limite de mensajes alcanzado']); exit; } }
            else $sessionToken = '';
        }
    }
    if ($sessionToken === '') {
        $nt = bin2hex(random_bytes(32)); $dm = parse_url($_SERVER['HTTP_ORIGIN']??$_SERVER['HTTP_REFERER']??'', PHP_URL_HOST)??'';
        $pdo->prepare("INSERT INTO chat_sessions (agent_id,session_token,ip_hash,domain) VALUES (?,?,?,?)")->execute([$agentId,$nt,$ipHash,$dm]);
        $sessionId=(int)$pdo->lastInsertId(); $sessionToken=$nt;
    }

    $spam = cSpam($pdo, $message, $ipHash, $sessionId);
    if ($spam['is_spam']) {
        cBizEvent($pdo, $agentId, $sessionId, 'spam_detected');
        echo json_encode(['reply'=>'Gracias por tu mensaje.','response'=>'Gracias por tu mensaje.','session'=>$sessionToken,'metadata'=>['intent'=>'spam_or_abuse','topic'=>'otro','lead_stage'=>'new','lead_score_delta'=>-100,'extracted_data'=>[],'next_action'=>'end_conversation','should_create_lead'=>false,'should_update_lead'=>false,'should_notify_admin'=>true,'conversation_summary_update'=>'Spam.']]);
        exit;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM usage_logs WHERE agent_id=? AND DATE(created_at)=CURDATE()");
    $st->execute([$agentId]);
    if ((int)$st->fetchColumn() >= (int)$agent['daily_message_limit']) { http_response_code(429); echo json_encode(['error'=>'Limite diario alcanzado']); exit; }

    $leadP = cLeadProfile($pdo, $agentId, $sessionId);
    $leadCtx = cLeadCtx($leadP);
    $ctxLim = (int)($agent['context_messages']??50);
    $st = $pdo->prepare("SELECT role,content FROM chat_messages WHERE session_id=? ORDER BY id DESC LIMIT $ctxLim");
    $st->execute([$sessionId]); $hist = array_reverse($st->fetchAll());
    $msgs = []; foreach ($hist as $r) $msgs[] = ['role'=>$r['role'],'content'=>$r['content']];
    $msgs[] = ['role'=>'user','content'=>$message];

    try { $result = $openAi->chat($agent, $msgs, $agentId, $leadCtx); }
    catch (RuntimeException $e) {
        AgentLogger::error("IA fallo: $agentId: ".$e->getMessage());
        $fb = 'Ahora mismo no pude procesar tu consulta. Dejame tu WhatsApp y te contacto.';
        $result = ['reply'=>$fb,'content'=>$fb,'metadata'=>['intent'=>'unknown','topic'=>'otro','lead_stage'=>$leadP['lead_stage']??'new','lead_score_delta'=>0,'extracted_data'=>[],'next_action'=>'offer_whatsapp','should_create_lead'=>false,'should_update_lead'=>false,'should_notify_admin'=>true,'conversation_summary_update'=>'Error IA.'],'tokens_input'=>0,'tokens_output'=>0,'model'=>'fallback','duration_ms'=>0];
    }

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("INSERT INTO chat_messages (session_id,role,content,tokens_used) VALUES (?,?,?,?)");
        $st->execute([$sessionId,'user',$message,null]);
        $st->execute([$sessionId,'assistant',$result['reply'],$result['tokens_output']]);
        $amid = (int)$pdo->lastInsertId();
        cMeta($pdo, $amid, $sessionId, $result['metadata']);
        $pdo->prepare("UPDATE chat_sessions SET message_count=message_count+1 WHERE id=?")->execute([$sessionId]);
        $pdo->prepare("INSERT INTO usage_logs (agent_id,session_id,ip_hash,tokens_input,tokens_output,model,duration_ms) VALUES (?,?,?,?,?,?,?)")
            ->execute([$agentId,$sessionId,$ipHash,$result['tokens_input'],$result['tokens_output'],$result['model'],$result['duration_ms']]);
        cUpdateLead($pdo, (int)$leadP['id'], $result, $agentId);
        cSync($pdo, (int)$leadP['id'], $sessionId);
        cEvents($pdo, $agentId, $sessionId, $result['metadata'], false);
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); AgentLogger::error("Error transaccion: ".$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Error interno']); exit; }

    echo json_encode(['reply'=>$result['reply'],'response'=>$result['reply'],'session'=>$sessionToken,'tokens_used'=>$result['tokens_output'],'model'=>$result['model'],'metadata'=>$result['metadata']]);
} catch (Throwable $e) { AgentLogger::error("Error chat: ".$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Error interno']); }
