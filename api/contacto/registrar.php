<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/mailer.php';

cors();
validateMethod('POST');

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
    $data = $isMultipart ? $_POST : getRequestBody();

    validateRequired($data, ['nombre', 'email', 'tipo', 'asunto', 'mensaje']);

    $nombre = trim($data['nombre']);
    $email = trim($data['email']);
    $telefono = isset($data['telefono']) ? trim($data['telefono']) : null;
    $tipo = trim($data['tipo']);
    $asunto = trim($data['asunto']);
    $mensaje = trim($data['mensaje']);

    $db = getConnection();
    $db->beginTransaction();

    $archivoContenido = null;
    $archivoNombre = null;
    $archivoTipo = null;

    if ($isMultipart && isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $archivo = $_FILES['archivo'];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $db->rollBack();
            sendError('Error al subir el archivo', 400);
        }

        $pesoMaximo = 2 * 1024 * 1024; // 2MB
        if ($archivo['size'] > $pesoMaximo) {
            $db->rollBack();
            sendError('El archivo supera el tamano permitido (2MB)', 400);
        }

        $mimePermitidos = ['application/pdf', 'image/png', 'image/jpeg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $mimePermitidos, true)) {
            $db->rollBack();
            sendError('Formato de archivo no permitido. Solo PDF, JPG o PNG.', 400);
        }

        $archivoContenido = file_get_contents($archivo['tmp_name']);
        $archivoNombre = $archivo['name'];
        $archivoTipo = $mime;
    }

    $sql = "
        INSERT INTO contacto (nombre, email, telefono, tipo, asunto, mensaje, archivo_blob, archivo_nombre, archivo_tipo)
        VALUES (:nombre, :email, :telefono, :tipo, :asunto, :mensaje, :archivo_blob, :archivo_nombre, :archivo_tipo)
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':nombre', $nombre);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':telefono', $telefono);
    $stmt->bindValue(':tipo', $tipo);
    $stmt->bindValue(':asunto', $asunto);
    $stmt->bindValue(':mensaje', $mensaje);
    $stmt->bindValue(':archivo_blob', $archivoContenido, $archivoContenido !== null ? PDO::PARAM_LOB : PDO::PARAM_NULL);
    $stmt->bindValue(':archivo_nombre', $archivoNombre, $archivoNombre !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':archivo_tipo', $archivoTipo, $archivoTipo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();

    $idContacto = (int)$db->lastInsertId();
    $db->commit();

    // Envío de correo (no bloquea el flujo si falla)
    try {
        $destino = SMTP_GMAIL_TO ?: SMTP_GMAIL_USER ?: $email;
        $asunto = 'Nueva solicitud de contacto';
        $html = "
            <h3>Nueva solicitud de contacto</h3>
            <p><strong>Nombre:</strong> {$nombre}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Teléfono:</strong> {$telefono}</p>
            <p><strong>Tipo:</strong> {$tipo}</p>
            <p><strong>Asunto:</strong> {$asunto}</p>
            <p><strong>Mensaje:</strong><br>" . nl2br(htmlspecialchars($mensaje)) . "</p>
        ";
        sendMailGmail([
            'to' => $destino,
            'subject' => $asunto,
            'html' => $html,
            'from' => SMTP_GMAIL_FROM ?: SMTP_GMAIL_USER,
            'from_name' => SMTP_GMAIL_FROM_NAME,
            'attachment_name' => $archivoNombre,
            'attachment_type' => $archivoTipo,
            'attachment_content' => $archivoContenido,
        ]);
    } catch (Exception $mailEx) {
        error_log('No se pudo enviar correo de contacto: ' . $mailEx->getMessage());
    }

    sendSuccess(['id_contacto' => $idContacto], 'Mensaje registrado');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
