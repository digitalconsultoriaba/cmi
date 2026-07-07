<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadSiteMediaRequest;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Storage;

class SiteMediaController extends Controller
{
    public function store(UploadSiteMediaRequest $request, Event $event)
    {
        $path = $request->file('file')->store('site', 'public');

        return ApiResponse::data([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }
}
