<?php
namespace CRM\Lib;

use Exception;
use PDO;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Mpdf\Mpdf;

class ProspeccionEngine {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function generarAsset(int $distribuidorId, int $landingId, int $templateId) {
        try {
            // 1. Validar Distribuidor y Landing
            $stmt = $this->pdo->prepare("SELECT slug FROM users WHERE id = ? AND active = 1");
            $stmt->execute([$distribuidorId]);
            $distribuidor = $stmt->fetch();
            
            if (!$distribuidor) {
                throw new Exception("Distribuidor no encontrado o inactivo.");
            }

            $stmt = $this->pdo->prepare("SELECT l.id, l.title FROM landings l INNER JOIN landing_subscriptions ls ON l.id = ls.landing_id WHERE l.id = ? AND ls.user_id = ?");
            $stmt->execute([$landingId, $distribuidorId]);
            $landing = $stmt->fetch();

            if (!$landing) {
                throw new Exception("Landing no encontrada o no pertenece al distribuidor.");
            }

            // 2. Obtener Template
            $stmt = $this->pdo->prepare("SELECT * FROM marketing_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch();

            if (!$template) {
                throw new Exception("Plantilla no encontrada.");
            }

            $baseImagePath = dirname(__DIR__) . '/' . ltrim($template['base_image_path'], '/');
            if (!file_exists($baseImagePath)) {
                throw new Exception("El archivo base de la plantilla no existe en el disco: " . $template['base_image_path']);
            }

            // 3. Construir URL Corta
            // Usamos el ID de la landing como 'tipo_campana' para mantenerlo único y simple
            $shortUrl = CRM_URL . "/r/" . urlencode($distribuidor['slug']) . "/" . $landingId;

            // 4. Generar QR en memoria
            // Para endroid/qr-code v5: ErrorCorrectionLevel::High
            $errorCorrectionLevel = class_exists(ErrorCorrectionLevelHigh::class) 
                ? new ErrorCorrectionLevelHigh() 
                : ErrorCorrectionLevel::High;

            $qrCode = QrCode::create($shortUrl)
                ->setErrorCorrectionLevel($errorCorrectionLevel)
                ->setSize((int)$template['qr_size'])
                ->setMargin(0);

            $writer = new PngWriter();
            $qrResult = $writer->write($qrCode);
            $qrData = $qrResult->getString();

            $isPdfBase = strtolower(pathinfo($baseImagePath, PATHINFO_EXTENSION)) === 'pdf';

            if ($isPdfBase) {
                // 5. Native PDF Stamping using mPDF & FPDI
                $mpdf = new Mpdf([
                    'margin_left' => 0,
                    'margin_right' => 0,
                    'margin_top' => 0,
                    'margin_bottom' => 0,
                    'margin_header' => 0,
                    'margin_footer' => 0,
                ]);

                $pagecount = $mpdf->SetSourceFile($baseImagePath);
                $tplId = $mpdf->ImportPage(1);
                $size = $mpdf->GetTemplateSize($tplId);

                // Set page dimensions to match the template exactly
                $mpdf->WriteHTML('<style>@page { size: ' . $size['width'] . 'pt ' . $size['height'] . 'pt; margin: 0; }</style>');
                $mpdf->AddPage($size['width'] . 'pt', $size['height'] . 'pt');
                $mpdf->UseTemplate($tplId);

                // Overlay QR Code absolute positioned on top of the imported PDF page
                $qrBase64 = base64_encode($qrData);
                $html = '<div style="position: absolute; left: ' . (int)$template['qr_x'] . 'pt; top: ' . (int)$template['qr_y'] . 'pt; width: ' . (int)$template['qr_size'] . 'pt; height: ' . (int)$template['qr_size'] . 'pt; margin: 0; padding: 0;">';
                $html .= '<img src="data:image/png;base64,' . $qrBase64 . '" style="width: 100%; height: 100%;" />';
                $html .= '</div>';

                $mpdf->WriteHTML($html);
                $mpdf->Output('asset_' . $landingId . '_' . time() . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
                exit;
            } else {
                // 5. Image Stamping using Intervention Image
                try {
                    $manager = new ImageManager(new ImagickDriver());
                } catch (Exception $e) {
                    $manager = new ImageManager(new GdDriver());
                }

                $image = $manager->read($baseImagePath);
                
                // Leer el QR como imagen y superponerlo
                $qrImage = $manager->read($qrData);
                
                // Intervention Image v3 place()
                $image->place($qrImage, 'top-left', (int)$template['qr_x'], (int)$template['qr_y']);

                $format = strtolower($template['output_format']);

                // 6. Manejo de Salida
                if ($format === 'jpg' || $format === 'jpeg') {
                    $encodedImage = $image->toJpeg(90);
                    
                    header('Content-Type: image/jpeg');
                    header('Content-Disposition: attachment; filename="asset_' . $landingId . '_' . time() . '.jpg"');
                    echo $encodedImage->toString();
                    exit;
                } elseif ($format === 'pdf') {
                    $encodedImage = $image->toJpeg(90)->toString();
                    $base64Image = base64_encode($encodedImage);
                    
                    // Inicializar Mpdf con márgenes en 0
                    $mpdf = new Mpdf([
                        'margin_left' => 0,
                        'margin_right' => 0,
                        'margin_top' => 0,
                        'margin_bottom' => 0,
                        'margin_header' => 0,
                        'margin_footer' => 0,
                        'format' => 'A4' // O se podría calcular según la imagen base
                    ]);

                    $mpdf->SetDisplayMode('fullpage');
                    
                    // Inyectar imagen con CSS para ocupar todo el 100%
                    $html = '<style>body, html { margin: 0; padding: 0; } img { width: 100%; height: 100%; object-fit: cover; }</style>';
                    $html .= '<img src="data:image/jpeg;base64,' . $base64Image . '">';
                    
                    $mpdf->WriteHTML($html);
                    $mpdf->Output('asset_' . $landingId . '_' . time() . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
                    exit;
                } else {
                    throw new Exception("Formato de salida no soportado: " . $format);
                }
            }

        } catch (Exception $e) {
            error_log("ProspeccionEngine Error: " . $e->getMessage());
            throw $e;
        }
    }
}
