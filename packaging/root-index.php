<?php
/*
 * Only shown when this server ignores the .htaccess file next to it, so
 * requests are not passed on to the public/ folder. Nothing here loads the
 * app or reads its settings.
 */
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>One more setting needed</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; background: #09090b; color: #ededf0; margin: 0; padding: 2rem 1rem; }
        main { max-width: 36rem; margin: 0 auto; }
        h1 { font-size: 1.4rem; }
        code { background: #1a1a1f; padding: 0.1rem 0.35rem; border-radius: 4px; color: #ffd27a; }
        li { margin: 0.4rem 0; }
    </style>
</head>
<body>
<main>
    <h1>The site is uploaded, but the web server needs one more setting</h1>
    <p>This server is not following the <code>.htaccess</code> file that came with the app. Do one of these, then reload this page:</p>
    <ol>
        <li><strong>Best:</strong> in cPanel → <em>Domains</em>, set this domain's document root to the <code>public</code> folder inside the app.</li>
        <li>Or ask your hosting support to <em>enable mod_rewrite and .htaccess (AllowOverride All)</em> for this site.</li>
    </ol>
    <p>Until then, do not enter any passwords on this site.</p>
</main>
</body>
</html>
