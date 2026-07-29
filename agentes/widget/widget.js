(function () {
    'use strict';

    var SCRIPT = document.currentScript || document.scripts[document.scripts.length - 1];
    var AGENT_ID = SCRIPT.getAttribute('data-agent-id') || '';

    var scriptUrl = SCRIPT.src;
    var API_BASE = scriptUrl.substring(0, scriptUrl.lastIndexOf('/widget/'));
    if (!API_BASE || API_BASE.indexOf('//') === -1) {
        API_BASE = window.location.origin;
    }
    // Go up one level: /crm/agentes → /crm
    var sep = API_BASE.lastIndexOf('/');
    if (sep > 8) { API_BASE = API_BASE.substring(0, sep); }

    if (!AGENT_ID || !/^ag_[a-f0-9]{28}$/.test(AGENT_ID)) {
        console.warn('[AgentHub] data-agent-id invalido o no encontrado');
        return;
    }

    var state = {
        session: null,
        messages: [],
        isOpen: false,
        configLoaded: false,
        style: 'bubble',
        voiceMode: 'none',
        elevenLabsAgentId: null,
        primaryColor: '#2563eb',
        agentName: 'Asistente',
        avatarUrl: null,
        // ElevenLabs voice state
        elConversation: null,
        elStatus: 'idle', // idle | connecting | connected | disconnected
        elMode: 'listening', // listening | speaking
    };

    // Recuperar sesión previa desde sessionStorage (sobrevive recargas dentro del mismo tab)
    try {
        var savedSession = sessionStorage.getItem('agw_session_' + AGENT_ID);
        if (savedSession && /^[a-f0-9]{64}$/.test(savedSession)) {
            state.session = savedSession;
        }
    } catch (e) { /* sessionStorage no disponible */ }

    var root, chatContainer, messagesEl, inputEl, sendBtn, typingEl, micBtn, voiceStatusEl;

    // Guardar sesión en sessionStorage
    function saveSession(token) {
        state.session = token;
        try { sessionStorage.setItem('agw_session_' + AGENT_ID, token); } catch (e) {}
    }

    // Petición streaming SSE — muestra tokens en tiempo real
    function streamRequest(message, session, onToken, onDone, onError) {
        var aborted = false;
        var timeout = setTimeout(function () {
            aborted = true;
            onError('La solicitud tard\u00f3 demasiado. Intenta de nuevo.');
        }, 40000);

        fetch(API_BASE + '/api/chat-stream.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ agent_id: AGENT_ID, message: message, session: session || null })
        }).then(function (res) {
            if (!res.ok) {
                clearTimeout(timeout);
                return res.json().then(function (d) { onError(d.error || 'Error del servidor'); });
            }
            var reader  = res.body.getReader();
            var decoder = new TextDecoder();
            var buf     = '';

            // Extractor de la clave "reply" del JSON que va llegando caracter a caracter.
            var rawAccum  = '';
            var inReply   = false;
            var escaped   = false;
            var replyBuf  = '';
            var headerBuf = '';

            function processToken(raw) {
                rawAccum += raw;
                for (var i = 0; i < raw.length; i++) {
                    var c = raw[i];
                    if (!inReply) {
                        headerBuf += c;
                        if (headerBuf.length > 10) headerBuf = headerBuf.slice(-10);
                        if (headerBuf.slice(-9) === '"reply":"' ||
                            headerBuf.slice(-10) === '"reply": "') {
                            inReply = true;
                            headerBuf = '';
                        }
                    } else {
                        if (escaped) {
                            escaped = false;
                            if (c === 'n')      { replyBuf += '\n'; onToken('\n'); }
                            else if (c === 't') { replyBuf += '\t'; onToken('\t'); }
                            else                { replyBuf += c;    onToken(c); }
                        } else if (c === '\\') {
                            escaped = true;
                        } else if (c === '"') {
                            inReply = false;
                        } else {
                            replyBuf += c;
                            onToken(c);
                        }
                    }
                }
            }

            function read() {
                return reader.read().then(function (result) {
                    if (aborted) return;
                    if (result.done) { clearTimeout(timeout); return; }

                    buf += decoder.decode(result.value, { stream: true });
                    var lines = buf.split('\n');
                    buf = lines.pop();

                    lines.forEach(function (line) {
                        line = line.trim();
                        if (line.slice(0, 6) !== 'data: ') return;
                        var json = line.slice(6);
                        try {
                            var evt = JSON.parse(json);
                            if (evt.error) { clearTimeout(timeout); onError(evt.error); return; }
                            if (evt.token) { processToken(evt.token); }
                            if (evt.done)  {
                                clearTimeout(timeout);
                                var finalReply = replyBuf.trim() || (evt.reply || '').trim();
                                onDone(evt.session, finalReply, evt.metadata);
                            }
                        } catch (e) { /* JSON parse error en chunk — ignorar */ }
                    });

                    return read();
                }).catch(function (err) {
                    if (!aborted) { clearTimeout(timeout); onError(err.message || 'Error de red'); }
                });
            }

            return read();
        }).catch(function (err) {
            clearTimeout(timeout);
            if (!aborted) onError(err.message || 'Error de conexi\u00f3n');
        });
    }

    function createUI() {
        root = document.createElement('div');
        root.className = 'agw-root';

        var cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.href = API_BASE + '/agentes/widget/widget.css';
        document.head.appendChild(cssLink);

        if (state.style === 'panel') {
            createPanelUI();
        } else {
            createBubbleUI();
        }

        document.body.appendChild(root);
    }

    function createBubbleUI() {
        var btn = document.createElement('button');
        btn.className = 'agw-bubble-btn';
        btn.style.backgroundColor = state.primaryColor;
        btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.88.54 3.63 1.48 5.12L2 22l5.12-1.48C8.37 21.46 10.12 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>';
        btn.setAttribute('aria-label', 'Abrir chat');
        btn.onclick = toggleChat;

        chatContainer = document.createElement('div');
        chatContainer.className = 'agw-bubble-overlay';
        chatContainer.style.display = 'none';
        buildChatContent();

        root.appendChild(btn);
        root.appendChild(chatContainer);
    }

    function createPanelUI() {
        var btn = document.createElement('button');
        btn.className = 'agw-panel-btn';
        btn.style.backgroundColor = state.primaryColor;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.88.54 3.63 1.48 5.12L2 22l5.12-1.48C8.37 21.46 10.12 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>';
        btn.setAttribute('aria-label', 'Abrir chat');
        btn.onclick = toggleChat;

        chatContainer = document.createElement('div');
        chatContainer.className = 'agw-panel-container';
        chatContainer.style.display = 'none';
        buildChatContent();

        root.appendChild(btn);
        root.appendChild(chatContainer);
    }

    function buildChatContent() {
        var header = document.createElement('div');
        header.className = 'agw-header';
        header.style.backgroundColor = state.primaryColor;
        header.innerHTML =
            '<div class="agw-header-info">' +
                '<span class="agw-avatar"></span>' +
                '<div><h3>' + escapeHtml(state.agentName) + '</h3><p>Online</p></div>' +
            '</div>' +
            '<button class="agw-close-btn">' +
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>' +
            '</button>';

        header.querySelector('.agw-close-btn').onclick = function () {
            closeChat();
        };

        messagesEl = document.createElement('div');
        messagesEl.className = 'agw-messages';

        var loading = document.createElement('div');
        loading.className = 'agw-typing';
        loading.innerHTML = '<span></span><span></span><span></span>';
        messagesEl.appendChild(loading);

        // Voice status bar (shown only when ElevenLabs mode is active/connecting)
        voiceStatusEl = document.createElement('div');
        voiceStatusEl.className = 'agw-voice-status';
        voiceStatusEl.style.cssText = 'display:none;padding:8px 16px;background:#f0f9ff;border-top:1px solid #bae6fd;font-size:12px;color:#0369a1;text-align:center;flex-shrink:0;align-items:center;justify-content:center;gap:8px;';

        var inputArea = document.createElement('div');
        inputArea.className = 'agw-input-area';

        inputEl = document.createElement('textarea');
        inputEl.placeholder = 'Escribe tu mensaje...';
        inputEl.rows = 1;
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(inputEl.value);
            }
        });
        inputEl.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        sendBtn = document.createElement('button');
        sendBtn.className = 'agw-send-btn';
        sendBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>';
        sendBtn.setAttribute('aria-label', 'Enviar mensaje');
        sendBtn.onclick = function () { sendMessage(inputEl.value); };

        inputArea.appendChild(inputEl);

        // Mic button — only built now; shown/hidden after config loads
        micBtn = document.createElement('button');
        micBtn.className = 'agw-mic-btn';
        micBtn.setAttribute('aria-label', 'Iniciar conversaci\u00f3n de voz');
        micBtn.title = 'Conversaci\u00f3n de voz con ElevenLabs';
        micBtn.innerHTML = '🎙️';
        micBtn.style.display = 'none'; // hidden until config confirms elevenlabs mode
        micBtn.onclick = toggleVoiceSession;

        inputArea.appendChild(micBtn);
        inputArea.appendChild(sendBtn);

        var footer = document.createElement('div');
        footer.className = 'agw-footer';
        footer.textContent = 'Asistente IA';

        chatContainer.appendChild(header);
        chatContainer.appendChild(messagesEl);
        chatContainer.appendChild(voiceStatusEl);
        chatContainer.appendChild(inputArea);
        chatContainer.appendChild(footer);
    }

    function toggleChat() {
        if (state.isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    function openChat() {
        state.isOpen = true;
        chatContainer.style.display = 'flex';
        if (state.configLoaded) showWelcome();

        setTimeout(function () {
            if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
        }, 100);
    }

    function closeChat() {
        state.isOpen = false;
        chatContainer.style.display = 'none';
        // End voice session if active when closing
        if (state.elConversation) {
            endVoiceSession();
        }
    }

    function fetchConfig(callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', API_BASE + '/api/agents.php?path=api/agents/' + AGENT_ID + '/config', true);
        xhr.onload = function() {
            console.log('[AgentHub] Config response status:', xhr.status);
            if (xhr.status === 200) {
                try {
                    var cfg = JSON.parse(xhr.responseText);
                    console.log('[AgentHub] Config received:', JSON.stringify(cfg));
                    if (cfg.primary_color) {
                        state.primaryColor = cfg.primary_color;
                        console.log('[AgentHub] Color set to:', state.primaryColor);
                    }
                    if (cfg.widget_style) state.style = cfg.widget_style;
                    if (cfg.name) state.agentName = cfg.name;
                    if (cfg.avatar_url) state.avatarUrl = cfg.avatar_url;
                    if (cfg.voice_mode) state.voiceMode = cfg.voice_mode;
                    if (cfg.elevenlabs_agent_id) state.elevenLabsAgentId = cfg.elevenlabs_agent_id;
                } catch(e) {
                    console.error('[AgentHub] Parse error:', e);
                }
            }
            if (callback) callback();
        };
        xhr.onerror = function() {
            console.error('[AgentHub] XHR error - could not reach:', API_BASE + '/api/agents.php?path=api/agents/' + AGENT_ID + '/config');
            if (callback) callback();
        };
        xhr.send();
    }

    function applyColors() {
        var c = state.primaryColor;
        var els = [
            '.agw-header',
            '.agw-bubble-btn',
            '.agw-panel-btn',
            '.agw-send-btn',
            '.agw-user-msg'
        ];
        for (var i = 0; i < els.length; i++) {
            var nodes = document.querySelectorAll(els[i]);
            for (var j = 0; j < nodes.length; j++) nodes[j].style.backgroundColor = c;
        }
        var styleId = 'agw-dynamic-colors';
        var existing = document.getElementById(styleId);
        if (existing) existing.remove();
        var style = document.createElement('style');
        style.id = styleId;
        style.textContent = '.agw-header { background: ' + c + ' !important; }' +
            '.agw-user-msg { background: ' + c + ' !important; }' +
            '.agw-send-btn { background: ' + c + ' !important; }' +
            '.agw-input-area textarea { border-color: ' + c + ' !important; }' +
            '.agw-input-area textarea:focus { border-color: ' + c + ' !important; }' +
            '.agw-send-btn:hover { background: ' + darkenColor(c) + ' !important; }' +
            '.agw-footer { color: ' + c + ' !important; }';
        document.head.appendChild(style);
        var nameEl = document.querySelector('.agw-header h3');
        if (nameEl) nameEl.textContent = state.agentName;
        var footerEl = document.querySelector('.agw-footer');
        if (footerEl) footerEl.textContent = state.agentName;
        var avatarEl = document.querySelector('.agw-avatar');
        if (avatarEl) {
            if (state.avatarUrl) {
                avatarEl.style.backgroundImage = 'url(' + state.avatarUrl + ')';
                avatarEl.style.backgroundSize = 'cover';
                avatarEl.style.backgroundPosition = 'center';
                avatarEl.innerHTML = '';
            } else {
                avatarEl.style.backgroundImage = '';
                avatarEl.innerHTML = '<span class="agw-avatar-initial">' + escapeHtml(state.agentName.charAt(0).toUpperCase()) + '</span>';
            }
        }
        // Show mic button if ElevenLabs mode is set (agent ID will be obtained via signed URL)
        if (micBtn) {
            if (state.voiceMode === 'elevenlabs') {
                micBtn.style.display = 'flex';
            } else {
                micBtn.style.display = 'none';
            }
        }
    }

    function showWelcome() {
        if (!messagesEl) return;

        var loading = messagesEl.querySelector('.agw-typing');
        if (loading) loading.remove();

        if (!messagesEl.querySelector('.agw-message')) {
            var welcome = document.createElement('div');
            welcome.className = 'agw-message agw-agent-msg';
            welcome.innerHTML = formatMessageText('\u00a1Hola! Soy ' + state.agentName + '. \u00bfEn qu\u00e9 puedo ayudarte?');
            messagesEl.appendChild(welcome);
        }
    }

    var isSending = false;

    function sendMessage(text) {
        text = (text || '').trim();
        if (!text) return;
        if (isSending) return;
        if (text.length > 1000) { showError('El mensaje es demasiado largo (max 1000 caracteres)'); return; }

        isSending = true;
        inputEl.disabled = true;
        sendBtn.disabled = true;

        addMessage(text, 'user');
        inputEl.value = '';
        inputEl.style.height = 'auto';
        showTyping();

        var msgEl = null;
        var accumulatedText = '';

        streamRequest(
            text,
            state.session,
            function (token) {
                if (!msgEl) {
                    hideTyping();
                    msgEl = document.createElement('div');
                    msgEl.className = 'agw-message agw-agent-msg';
                    messagesEl.appendChild(msgEl);
                }
                accumulatedText += token;
                msgEl.innerHTML = formatMessageText(accumulatedText);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            },
            function (sessionToken, finalReply, metadata) {
                if (sessionToken) saveSession(sessionToken);

                if (!msgEl) {
                    hideTyping();
                    msgEl = document.createElement('div');
                    msgEl.className = 'agw-message agw-agent-msg';
                    msgEl.innerHTML = formatMessageText(finalReply || 'No recib\u00ed respuesta v\u00e1lida.');
                    messagesEl.appendChild(msgEl);
                } else if (finalReply) {
                    msgEl.innerHTML = formatMessageText(finalReply);
                }

                messagesEl.scrollTop = messagesEl.scrollHeight;

                isSending = false;
                inputEl.disabled = false;
                sendBtn.disabled = false;
                inputEl.focus();
            },
            function (errMsg) {
                hideTyping();
                showError(errMsg);
                if (errMsg && (errMsg.indexOf('Sesion') !== -1 || errMsg.indexOf('sesi') !== -1)) {
                    state.session = null;
                    try { sessionStorage.removeItem('agw_session_' + AGENT_ID); } catch (e) {}
                }
                isSending = false;
                inputEl.disabled = false;
                sendBtn.disabled = false;
                inputEl.focus();
            }
        );
    }

    function addMessage(text, role) {
        var msg = document.createElement('div');
        msg.className = 'agw-message ' + (role === 'user' ? 'agw-user-msg' : 'agw-agent-msg');
        msg.innerHTML = formatMessageText(text);

        messagesEl.appendChild(msg);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function showTyping() {
        hideTyping();
        typingEl = document.createElement('div');
        typingEl.className = 'agw-typing';
        typingEl.innerHTML = '<span></span><span></span><span></span>';
        messagesEl.appendChild(typingEl);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function hideTyping() {
        if (typingEl && typingEl.parentNode) {
            typingEl.parentNode.removeChild(typingEl);
            typingEl = null;
        }
    }

    function showError(text) {
        var err = document.createElement('div');
        err.className = 'agw-error-msg';
        err.textContent = text;
        messagesEl.appendChild(err);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatMessageText(text) {
        if (!text) return '';
        var html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        
        return html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    }

    function darkenColor(hex) {
        var r = parseInt(hex.slice(1,3), 16), g = parseInt(hex.slice(3,5), 16), b = parseInt(hex.slice(5,7), 16);
        r = Math.max(0, Math.floor(r * 0.85));
        g = Math.max(0, Math.floor(g * 0.85));
        b = Math.max(0, Math.floor(b * 0.85));
        return '#' + [r, g, b].map(function(v) { return v.toString(16).padStart(2, '0'); }).join('');
    }

    // ============================================================
    //  ELEVENLABS CONVERSATIONAL AI
    // ============================================================

    var elSDKLoaded = false;
    var elSDKLoading = false;
    var elSDKCallbacks = [];
    var EL_SDK_CDN = 'https://cdn.jsdelivr.net/npm/@elevenlabs/client@1.9.0/dist/lib.iife.js';

    function loadElevenLabsSDK(callback) {
        if (elSDKLoaded) { callback(null); return; }
        elSDKCallbacks.push(callback);
        if (elSDKLoading) return;
        elSDKLoading = true;
        var s = document.createElement('script');
        s.src = EL_SDK_CDN;
        s.onload = function () {
            elSDKLoaded = true;
            elSDKLoading = false;
            elSDKCallbacks.forEach(function(cb) { cb(null); });
            elSDKCallbacks = [];
        };
        s.onerror = function () {
            elSDKLoading = false;
            var err = new Error('No se pudo cargar el SDK de ElevenLabs desde CDN.');
            elSDKCallbacks.forEach(function(cb) { cb(err); });
            elSDKCallbacks = [];
        };
        document.head.appendChild(s);
    }

    function setVoiceStatus(status, mode) {
        if (status !== undefined) state.elStatus = status;
        if (mode !== undefined) state.elMode = mode;

        if (!voiceStatusEl || !micBtn) return;

        if (state.elStatus === 'idle' || state.elStatus === 'disconnected') {
            voiceStatusEl.style.display = 'none';
            micBtn.classList.remove('listening');
            micBtn.innerHTML = '🎙️';
            micBtn.title = 'Iniciar conversaci\u00f3n de voz';
        } else if (state.elStatus === 'connecting') {
            voiceStatusEl.style.display = 'flex';
            voiceStatusEl.innerHTML = '<span style="animation:agwTypingDot 1s infinite">\u25cf</span>&nbsp; Conectando con el agente de voz...';
            micBtn.classList.add('listening');
            micBtn.innerHTML = '⏳';
            micBtn.title = 'Conectando...';
        } else if (state.elStatus === 'connected') {
            voiceStatusEl.style.display = 'flex';
            if (state.elMode === 'speaking') {
                voiceStatusEl.innerHTML = '\ud83d\udd0a&nbsp; El agente est\u00e1 hablando...';
                micBtn.classList.remove('listening');
                micBtn.innerHTML = '🔇';
                micBtn.title = 'Finalizar voz';
            } else {
                voiceStatusEl.innerHTML = '🎤&nbsp; Escuchando... (habla ahora)';
                micBtn.classList.add('listening');
                micBtn.innerHTML = '⏹️';
                micBtn.title = 'Detener conversaci\u00f3n de voz';
            }
        }
    }

    function toggleVoiceSession() {
        if (state.elConversation) {
            endVoiceSession();
        } else {
            startVoiceSession();
        }
    }

    function startVoiceSession() {
        setVoiceStatus('connecting');

        // Request mic first so the browser doesn't block startSession
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function () {
            loadElevenLabsSDK(function(err) {
                if (err) {
                    setVoiceStatus('idle');
                    showError('Error al cargar el SDK de ElevenLabs: ' + err.message);
                    return;
                }

                // The IIFE build exposes window.ElevenLabsClient
                var EL = window.ElevenLabsClient;
                var Conversation = EL && EL.Conversation;

                if (!Conversation) {
                    setVoiceStatus('idle');
                    showError('SDK de ElevenLabs no disponible. Verifica la versi\u00f3n del CDN.');
                    console.error('[AgentHub] window.ElevenLabsClient:', window.ElevenLabsClient);
                    return;
                }

                // Obtener signed URL de nuestro backend (la API key nunca sale del servidor)
                fetch(API_BASE + '/api/elevenlabs.php?action=signed-url&agent_id=' + encodeURIComponent(AGENT_ID))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (!data.signed_url) {
                            setVoiceStatus('idle');
                            showError('No se pudo obtener la URL de sesi\u00f3n de voz: ' + (data.error || 'Error desconocido'));
                            return;
                        }

                        Conversation.startSession({
                            signedUrl: data.signed_url,
                            onConnect: function() {
                                console.log('[AgentHub] ElevenLabs connected');
                                setVoiceStatus('connected', 'listening');
                                addMessage('\ud83c\udfa4 Conversaci\u00f3n de voz iniciada. Habla con el agente.', 'agent');
                            },
                            onDisconnect: function() {
                                console.log('[AgentHub] ElevenLabs disconnected');
                                state.elConversation = null;
                                setVoiceStatus('disconnected');
                                setTimeout(function() { setVoiceStatus('idle'); }, 1500);
                                addMessage('Conversaci\u00f3n de voz finalizada.', 'agent');
                            },
                            onModeChange: function(modeData) {
                                var newMode = (modeData && modeData.mode) ? modeData.mode : 'listening';
                                setVoiceStatus('connected', newMode);
                            },
                            onMessage: function(msgData) {
                                console.log('[AgentHub] ElevenLabs message:', msgData);
                            },
                            onError: function(errMsg) {
                                console.error('[AgentHub] ElevenLabs error:', errMsg);
                                showError('Error en la conversaci\u00f3n de voz: ' + (errMsg || 'desconocido'));
                                state.elConversation = null;
                                setVoiceStatus('idle');
                            },
                            onStatusChange: function(statusData) {
                                console.log('[AgentHub] ElevenLabs status:', statusData);
                            },
                        }).then(function(conv) {
                            state.elConversation = conv;
                        }).catch(function(e) {
                            console.error('[AgentHub] ElevenLabs startSession error:', e);
                            setVoiceStatus('idle');
                            if (e && e.message && e.message.toLowerCase().includes('permission')) {
                                showError('Permiso de micr\u00f3fono denegado. Habil\u00edtalo en la configuraci\u00f3n del navegador.');
                            } else {
                                showError('No se pudo iniciar la sesi\u00f3n de voz: ' + (e.message || e));
                            }
                        });
                    })
                    .catch(function(fetchErr) {
                        setVoiceStatus('idle');
                        showError('Error de conexi\u00f3n al obtener la URL de voz: ' + fetchErr.message);
                    });
            });
        }).catch(function(micErr) {
            setVoiceStatus('idle');
            showError('Permiso de micr\u00f3fono denegado. Habil\u00edtalo en la configuraci\u00f3n del navegador.');
            console.error('[AgentHub] Mic permission error:', micErr);
        });
    }


    function endVoiceSession() {
        if (state.elConversation) {
            try {
                state.elConversation.endSession();
            } catch(e) {
                console.warn('[AgentHub] Error ending ElevenLabs session:', e);
            }
            state.elConversation = null;
        }
        setVoiceStatus('idle');
    }

    // ============================================================

    var customColor = SCRIPT.getAttribute('data-primary-color');
    var customStyle = SCRIPT.getAttribute('data-style');
    var customName = SCRIPT.getAttribute('data-agent-name');

    if (customColor) state.primaryColor = customColor;
    if (customStyle) state.style = customStyle;
    if (customName) state.agentName = customName;

    function initWidget() {
        console.log('[AgentHub] Initializing with color:', state.primaryColor, 'style:', state.style);
        createUI();
        applyColors();
        console.log('[AgentHub] UI created, fetching config...');
        fetchConfig(function () {
            console.log('[AgentHub] Config callback - voiceMode:', state.voiceMode, 'elevenLabsAgentId:', state.elevenLabsAgentId);
            applyColors();
            state.configLoaded = true;
            if (state.isOpen) showWelcome();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }

})();
