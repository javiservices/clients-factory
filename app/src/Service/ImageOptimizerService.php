<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageOptimizerService
{
    /**
     * Procesa, escala y comprime una imagen subida, guardándola en formato WebP optimizado
     * y eliminando la copia original no comprimida.
     *
     * @param UploadedFile $file Archivo subido temporal
     * @param string $targetDirectory Directorio de destino final (uploads/clients)
     * @param int $maxDimension Dimensión máxima en píxeles (ancho o alto, por defecto 600px)
     * @param int $quality Calidad de compresión WebP (1-100, 82 ofrece máxima nitidez con peso mínimo)
     * @return string Nombre del archivo optimizado generado
     */
    public function optimizeUploadedPhoto(
        UploadedFile $file,
        string $targetDirectory,
        int $maxDimension = 600,
        int $quality = 82
    ): string {
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $sourcePath = $file->getRealPath() ?: $file->getPathname();
        $newFilename = uniqid('client_', true) . '.webp';
        $targetPath = rtrim($targetDirectory, '/') . '/' . $newFilename;

        $optimized = $this->processAndSave($sourcePath, $targetPath, $maxDimension, $quality);

        if (!$optimized) {
            // Fallback si GD no pudiera procesar el archivo: mover como JPG/PNG tradicional
            $fallbackFilename = uniqid('client_', true) . '.' . ($file->guessExtension() ?: 'jpg');
            $file->move($targetDirectory, $fallbackFilename);
            return $fallbackFilename;
        }

        return $newFilename;
    }

    /**
     * Optimiza y comprime un archivo ya existente en el servidor, convirtiéndolo a WebP
     * y eliminando el archivo original de gran tamaño.
     *
     * @param string $filePath Ruta absoluta al archivo existente
     * @param int $maxDimension Dimensión máxima (600px)
     * @param int $quality Calidad WebP (82)
     * @return string|null Nombre del nuevo archivo generado, o null si falló
     */
    public function optimizeExistingFile(
        string $filePath,
        int $maxDimension = 600,
        int $quality = 82
    ): ?string {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        $dir = dirname($filePath);
        $newFilename = uniqid('client_', true) . '.webp';
        $targetPath = $dir . '/' . $newFilename;

        $success = $this->processAndSave($filePath, $targetPath, $maxDimension, $quality);

        if ($success) {
            // Eliminar el archivo original pesado inmediatamente
            if (file_exists($filePath) && realpath($filePath) !== realpath($targetPath)) {
                @unlink($filePath);
            }
            return $newFilename;
        }

        return null;
    }

    /**
     * Lógica central de redimensionamiento con interpolación bicúbica, corrección de orientación EXIF y compresión WebP.
     */
    private function processAndSave(
        string $sourcePath,
        string $targetPath,
        int $maxDimension,
        int $quality
    ): bool {
        // Asegurar memoria suficiente para decodificar fotos de hasta 20-30MB en memoria
        $originalMemory = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');

        try {
            $imageInfo = @getimagesize($sourcePath);
            if (!$imageInfo) {
                return false;
            }

            [$origWidth, $origHeight, $imageType] = $imageInfo;

            // 1. Cargar la imagen según su tipo
            $sourceImage = match ($imageType) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
                IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
                IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
                IMAGETYPE_BMP  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false,
                default        => false,
            };

            if (!$sourceImage) {
                return false;
            }

            // 2. Corregir orientación EXIF (cámaras de móvil / iPhone / Samsung)
            $sourceImage = $this->fixOrientation($sourcePath, $sourceImage, $imageType);
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            // 3. Calcular nuevas dimensiones proporcionales
            $newWidth = $origWidth;
            $newHeight = $origHeight;

            if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
                $ratio = $origWidth / $origHeight;
                if ($ratio > 1) {
                    // Imagen horizontal
                    $newWidth = $maxDimension;
                    $newHeight = (int) round($maxDimension / $ratio);
                } else {
                    // Imagen vertical o cuadrada
                    $newHeight = $maxDimension;
                    $newWidth = (int) round($maxDimension * $ratio);
                }
            }

            // 4. Crear lienzo redimensionado con soporte de transparencia
            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);

            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
            imagealphablending($canvas, true);

            // Redimensionado de alta calidad
            imagecopyresampled(
                $canvas,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $origWidth, $origHeight
            );

            // 5. Guardar comprimido en WebP (o JPEG si no hubiera WebP)
            $saved = false;
            if (function_exists('imagewebp')) {
                // WebP a calidad 82 ofrece la mayor ratio de compresión con cero pérdida visual
                $saved = @imagewebp($canvas, $targetPath, $quality);
            } elseif (function_exists('imagejpeg')) {
                $saved = @imagejpeg($canvas, $targetPath, $quality);
            }

            @imagedestroy($sourceImage);
            @imagedestroy($canvas);

            return $saved && file_exists($targetPath) && filesize($targetPath) > 0;
        } catch (\Throwable) {
            return false;
        } finally {
            if ($originalMemory) {
                @ini_set('memory_limit', $originalMemory);
            }
        }
    }

    /**
     * Corrige la rotación automática según los metadatos EXIF del sensor de la cámara.
     */
    private function fixOrientation(string $sourcePath, \GdImage $image, int $imageType): \GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($sourcePath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3: // 180 rotate
                        $rotated = imagerotate($image, 180, 0);
                        if ($rotated) {
                            imagedestroy($image);
                            return $rotated;
                        }
                        break;
                    case 6: // 90 rotate clockwise (270 counter-clockwise)
                        $rotated = imagerotate($image, -90, 0);
                        if ($rotated) {
                            imagedestroy($image);
                            return $rotated;
                        }
                        break;
                    case 8: // 90 rotate counter-clockwise
                        $rotated = imagerotate($image, 90, 0);
                        if ($rotated) {
                            imagedestroy($image);
                            return $rotated;
                        }
                        break;
                }
            }
        } catch (\Throwable) {}

        return $image;
    }
}
