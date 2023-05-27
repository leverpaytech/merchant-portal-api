<html>
    <head>Upload Test</head>
    <body>
        <form method="post" action="{{ route('addKyc') }}" enctype="multipart/form-data">
            @csrf
            <p><input type="text" name="document_name"></p> 
            <p><input type="file" name="document_link"></p> 
            <p><input type="submit" value="upload" name="submit"></p> 
        </form>
    </body>
</html>