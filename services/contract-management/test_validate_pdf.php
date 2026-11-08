<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a dummy PDF with %PDF header like jsPDF creates
$pdfContent = "%PDF-1.3\n%\n3 0 obj\n<</Type /Page /Parent 1 0 R /Resources 2 0 R /Contents 4 0 R>>\nendobj\n";
$temp = tempnam(sys_get_temp_dir(), 'test_pdf_');
file_put_contents($temp, $pdfContent);

$file = new Illuminate\Http\UploadedFile(
    $temp,
    'Contract_Agreement.pdf',
    'application/pdf',
    null,
    true
);

$req = Illuminate\Http\Request::create('/api/documents/upload', 'POST', [], [], ['file' => $file]);
$req->headers->set('Accept', 'application/json');
$req->attributes->set('auth_id', 350);
$req->attributes->set('auth_role', 'Sales');

// Check validation rule
$validator = Illuminate\Support\Facades\Validator::make(['file' => $file], [
    'file' => [
        'required',
        'file',
        'max:10240',
        new App\Rules\ValidContractDocument(),
    ]
]);

if ($validator->fails()) {
    echo "Validator FAILS:\n";
    print_r($validator->errors()->all());
} else {
    echo "Validator PASSES!\n";
}

@unlink($temp);
