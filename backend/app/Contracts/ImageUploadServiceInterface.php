<?php

namespace App\Contracts;


interface ImageUploadServiceInterface
{
    public function upload($file, string $path): string;
    public function delete(?string $path): void;
}
