<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/oauth_handler.php';

// ============================================
// PEGA AQUÍ TUS TOKENS DE BRIGHTSPACE/POSTMAN
// ============================================
$access_token = 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjIwMmUwMTA0LWE2YTItNDg5OC05YzI1LWQzYTFiNGNmMDc4ZiIsInR5cCI6IkpXVCJ9.eyJuYmYiOjE3NjE2NjgzNzQsImV4cCI6MTc2MTY3MTk3NCwiaXNzIjoiaHR0cHM6Ly9hcGkuYnJpZ2h0c3BhY2UuY29tL2F1dGgiLCJhdWQiOiJodHRwczovL2FwaS5icmlnaHRzcGFjZS5jb20vYXV0aC90b2tlbiIsInN1YiI6IjMwNDIiLCJ0ZW5hbnRpZCI6IjU1N2UwOTllLTRjYTEtNDgwYy1hYmFlLTNlM2UyZjgwMjdjYSIsImF6cCI6IjVmZTY1ZjRlLWEwMjEtNDc2MC05ODEwLWE4ZTUyMzgxMGQyMSIsInNjb3BlIjoiY29udGVudDptb2R1bGVzOnJlYWQgY29udGVudDp0b2M6cmVhZCBjb3JlOio6KiBlbnJvbGxtZW50Om9yZ3VuaXQ6cmVhZCBncmFkZXM6Z3JhZGVvYmplY3RzOnJlYWQgZ3JhZGVzOmdyYWRlczpyZWFkIiwianRpIjoiYmYyMzhmYzYtOTQ0Yy00YTc1LTg5Y2QtZDdhZjE2MDVjZWQ5In0.pzGgCWASbLtW8iXb3wGFFAfZbQ3SLK4lY0qI4BQpWgk0rTtrkWSclKfWOiw_gCU4DymYXcAoSmDHcBGnmexbFqwpo1YZEYbyYsnPoJYEVDTPbkaIERecqISSA_h1k7KNqsPX5kbqT-xpZXECyuoZrxUprfiwX_JU1S0slpLN-E47y_j-t5a5yJNALSywxk9oZsKvGKPL5qA9BQbPXRBDeuFk4JqDdDDyyNZj67Qvb6WAAbkCAm4ev0Nnx_ocUpPX9fKTUCttJd-g9Llq9UTZZFTE6KrIksab3nTosvoKvIPV2cxtb7nUDCSd7ONG4D2H8t2jADZq3v553sCgJYpqNQ';
$refresh_token = 'rt.us-east-1.r8Tuzy0KWwpUMbRfPwj3fgSQTbxRoiNsE13nrCNQzEI';
$expires_in = 3600; // 1 hora

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Token - TESA Syllabus Monitor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success h2 {
            margin-bottom: 10px;
            font-size: 1.5em;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .error h2 {
            margin-bottom: 10px;
            font-size: 1.5em;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info h3, .info h4 {
            margin-bottom: 15px;
            color: #1565c0;
        }
        .info ul {
            list-style: none;
            padding: 0;
        }
        .info ul li {
            padding: 8px 0;
            border-bottom: 1px solid #bbdefb;
        }
        .info ul li:last-child {
            border-bottom: none;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 10px 20px 0;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
            font-size: 0.9em;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        hr {
            border: none;
            border-top: 2px solid #e9ecef;
            margin: 30px 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            background: #28a745;
            color: white;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .badge.expired {
            background: #dc3545;
        }
        .badge.warning {
            background: #ffc107;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .loading::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Configuración de Token OAuth</h1>
            <p>TESA Syllabus Monitor - Brightspace API</p>
        </div>
        
        <div class="content">
        
        <?php
        // Verificar que se hayan pegado los tokens
        if ($access_token === 'PEGA_AQUI_TU_ACCESS_TOKEN' || $refresh_token === 'PEGA_AQUI_TU_REFRESH_TOKEN') {
            echo '<div class="warning">';
            echo '<h2>⚠️ Tokens no configurados</h2>';
            echo '<p>Por favor, edita el archivo <code>auth_manual.php</code> y pega tus tokens en las líneas 7 y 8.</p>';
            echo '<ol style="margin-top: 15px; margin-left: 20px;">';
            echo '<li>Ve a Brightspace y genera un nuevo token</li>';
            echo '<li>Copia el <strong>access_token</strong> y el <strong>refresh_token</strong></li>';
            echo '<li>Pégalos en este archivo PHP</li>';
            echo '<li>Guarda y recarga esta página</li>';
            echo '</ol>';
            echo '</div>';
            exit;
        }
        
        try {
            $oauth = new OAuthHandler();
            
            echo '<div class="info">';
            echo '<h3>📋 Análisis del Token JWT</h3>';
            
            // Decodificar el JWT
            $token_parts = explode('.', $access_token);
            if (count($token_parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1])), true);
                
                $tiempo_actual = time();
                $tiempo_expiracion = $payload['exp'] ?? 0;
                $diferencia = $tiempo_expiracion - $tiempo_actual;
                
                echo '<table>';
                echo '<tr><th>Campo</th><th>Valor</th></tr>';
                echo '<tr><td><strong>Subject (User ID)</strong></td><td>' . ($payload['sub'] ?? 'NO DEFINIDO') . '</td></tr>';
                echo '<tr><td><strong>Tenant ID</strong></td><td>' . ($payload['tenantid'] ?? 'NO DEFINIDO') . '</td></tr>';
                echo '<tr><td><strong>Client ID (azp)</strong></td><td>' . ($payload['azp'] ?? 'NO DEFINIDO') . '</td></tr>';
                echo '<tr><td><strong>Scopes</strong></td><td><code>' . ($payload['scope'] ?? 'NO DEFINIDO') . '</code></td></tr>';
                echo '<tr><td><strong>Emitido (nbf)</strong></td><td>' . date('Y-m-d H:i:s', $payload['nbf'] ?? 0) . '</td></tr>';
                echo '<tr><td><strong>Expira (exp)</strong></td><td>' . date('Y-m-d H:i:s', $tiempo_expiracion) . '</td></tr>';
                echo '<tr><td><strong>Hora actual servidor</strong></td><td>' . date('Y-m-d H:i:s', $tiempo_actual) . '</td></tr>';
                
                if ($diferencia > 0) {
                    $minutos = floor($diferencia / 60);
                    echo '<tr><td><strong>Estado</strong></td><td><span class="badge">✅ VÁLIDO</span> (quedan ' . $minutos . ' minutos)</td></tr>';
                } elseif ($diferencia > -300) {
                    echo '<tr><td><strong>Estado</strong></td><td><span class="badge warning">⚠️ RECIÉN EXPIRADO</span> (hace ' . abs($diferencia) . ' segundos)</td></tr>';
                } else {
                    echo '<tr><td><strong>Estado</strong></td><td><span class="badge expired">❌ EXPIRADO</span> (hace ' . abs(floor($diferencia / 60)) . ' minutos)</td></tr>';
                }
                
                echo '</table>';
                
                // Advertencia si está expirado
                if ($diferencia <= 0) {
                    echo '<div class="warning" style="margin-top: 20px;">';
                    echo '<h4>⚠️ El token ya expiró</h4>';
                    echo '<p>Necesitas generar un nuevo token desde Brightspace. Este token no funcionará.</p>';
                    echo '<ol style="margin-left: 20px; margin-top: 10px;">';
                    echo '<li>Ve a Brightspace OAuth</li>';
                    echo '<li>Genera un nuevo Access Token</li>';
                    echo '<li>Actualiza este archivo con el nuevo token</li>';
                    echo '</ol>';
                    echo '</div>';
                }
            }
            echo '</div>';
            
            // Guardar el token
            echo '<h3>💾 Guardando token en la base de datos...</h3>';
            $result = $oauth->setManualToken($access_token, $refresh_token, $expires_in);
            
            if ($result) {
                $expiry_date = date('Y-m-d H:i:s', time() + $expires_in);
                echo '<div class="success">';
                echo '<h2>✅ Token guardado exitosamente</h2>';
                echo '<p><strong>Expira en:</strong> ' . $expiry_date . '</p>';
                echo '<p><strong>Access Token (primeros 50 caracteres):</strong><br>';
                echo '<code>' . substr($access_token, 0, 50) . '...</code></p>';
                echo '<p><strong>Refresh Token:</strong><br>';
                echo '<code>' . $refresh_token . '</code></p>';
                echo '</div>';
                
                echo '<a href="index.php" class="btn">🚀 Ir al Dashboard</a>';
                echo '<a href="auth_manual.php" class="btn btn-secondary">🔄 Probar otro token</a>';
                
                // Probar conexión con la API
                echo '<hr>';
                echo '<h3>🧪 Probando conexión con Brightspace API...</h3>';
                echo '<div class="loading">Conectando con la API</div>';
                
                try {
                    require_once INCLUDES_PATH . '/api_brightspace.php';
                    $api = new ApiBrightspace();
                    
                    echo '<script>document.querySelector(".loading").style.display="none";</script>';
                    
                    $periodos = $api->getPeriodos();
                    
                    echo '<div class="success">';
                    echo '<h2>✅ Conexión exitosa con Brightspace</h2>';
                    echo '<p>Se encontraron <strong>' . count($periodos) . ' períodos/semestres</strong>.</p>';
                    echo '</div>';
                    
                    if (!empty($periodos)) {
                        echo '<div class="info">';
                        echo '<h4>📅 Primeros 5 períodos encontrados:</h4>';
                        echo '<table>';
                        echo '<tr><th>ID</th><th>Nombre</th><th>Código</th></tr>';
                        
                        $count = 0;
                        foreach ($periodos as $periodo) {
                            if ($count >= 5) break;
                            echo '<tr>';
                            echo '<td>' . ($periodo['Identifier'] ?? 'N/A') . '</td>';
                            echo '<td>' . ($periodo['Name'] ?? 'N/A') . '</td>';
                            echo '<td>' . ($periodo['Code'] ?? 'N/A') . '</td>';
                            echo '</tr>';
                            $count++;
                        }
                        echo '</table>';
                        echo '</div>';
                    }
                    
                    echo '<div class="success">';
                    echo '<h3>🎉 Todo está configurado correctamente</h3>';
                    echo '<p>Ya puedes usar el sistema. El token se renovará automáticamente cuando sea necesario.</p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    echo '<script>document.querySelector(".loading").style.display="none";</script>';
                    echo '<div class="error">';
                    echo '<h2>❌ Error al conectar con la API</h2>';
                    echo '<p><strong>Mensaje:</strong> ' . $e->getMessage() . '</p>';
                    echo '<h4>Posibles causas:</h4>';
                    echo '<ul style="margin-left: 20px;">';
                    echo '<li>El token ya expiró (verifica arriba)</li>';
                    echo '<li>Los scopes no son suficientes</li>';
                    echo '<li>Problema de conexión con Brightspace</li>';
                    echo '<li>El Client ID no coincide</li>';
                    echo '</ul>';
                    echo '<h4>Solución:</h4>';
                    echo '<p>Genera un nuevo token desde Brightspace y actualiza este archivo.</p>';
                    echo '</div>';
                }
                
            } else {
                echo '<div class="error">';
                echo '<h2>❌ Error al guardar el token</h2>';
                echo '<p>No se pudo guardar el token en la base de datos.</p>';
                echo '<p>Verifica que la base de datos esté configurada correctamente.</p>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h2>❌ Error general:</h2>';
            echo '<p>' . $e->getMessage() . '</p>';
            echo '<details>';
            echo '<summary>Ver detalles técnicos</summary>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
            echo '</details>';
            echo '</div>';
        }
        ?>
        
        <hr>
        <div class="info">
            <h4>📝 Instrucciones:</h4>
            <ol style="margin-left: 20px;">
                <li>Edita este archivo (<code>auth_manual.php</code>)</li>
                <li>Pega tu <strong>access_token</strong> y <strong>refresh_token</strong> en las líneas 7-8</li>
                <li>Guarda el archivo</li>
                <li>Recarga esta página</li>
                <li>Si la conexión es exitosa, ve al Dashboard</li>
                <li><strong>Opcional:</strong> Elimina este archivo después de configurar</li>
            </ol>
        </div>
        
        </div>
    </div>
</body>
</html>