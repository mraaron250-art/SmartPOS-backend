<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Serves the OpenAPI contract and a Swagger UI page.
 */
final class DocsController extends Controller
{
    private const SWAGGER_UI = 'https://unpkg.com/swagger-ui-dist@5.34.0';

    public function ui(Request $request): Response
    {
        $cdn = self::SWAGGER_UI;
        $html = <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>SmartPOS API</title>
              <link rel="stylesheet" href="{$cdn}/swagger-ui.css">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="{$cdn}/swagger-ui-bundle.js" crossorigin></script>
              <script>
                window.ui = SwaggerUIBundle({ url: '/api/docs/openapi.yaml', dom_id: '#swagger-ui', persistAuthorization: true });
              </script>
            </body>
            </html>
            HTML;

        return Response::html($html)
            ->withHeader('X-Frame-Options', 'DENY');
    }

    public function spec(Request $request): Response
    {
        $yaml = (string) file_get_contents(dirname(__DIR__, 3) . '/docs/api/openapi.yaml');

        return (new Response($yaml, 200, ['Content-Type' => 'application/yaml; charset=utf-8']))
            ->withHeader('Cache-Control', 'public, max-age=300');
    }
}
