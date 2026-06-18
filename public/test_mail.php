<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

try {
    \Illuminate\Support\Facades\Mail::raw('Test email from Laravel test script.', function ($message) {
        $message->to('supportlnt199@gmail.com')
                ->subject('Test Email');
    });
    echo "SUCCESS: Email sent successfully!";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
