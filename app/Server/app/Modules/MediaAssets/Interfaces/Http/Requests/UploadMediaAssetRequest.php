<?php

namespace App\Modules\MediaAssets\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'context_type' => ['nullable', 'in:catalog_product,cart_item,live_art_request,general'],
            'context_id' => ['nullable', 'integer', 'min:1', 'required_with:context_type'],
            'visibility' => ['nullable', 'in:public,private'],
        ];
    }
}
