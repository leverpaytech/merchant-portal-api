<!-- admin_swagger_documentation.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeverChain Swagger Documentation</title>
</head>
<body>
    <!-- You can include any additional HTML structure or styling here -->
    
    <!-- Load Swagger UI -->
    <div id="swagger-ui"></div>
    
    <!-- Load Swagger JSON -->
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '/api/v1/merchant/swagger-json',
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: 'StandaloneLayout'
            })
        }
    </script>
    
    <!-- You may include additional JavaScript or customize as needed -->
</body>
</html>
