<?php

namespace App\Http\Controllers;

class PlaceholderController extends Controller
{
    public function websites()
    {
        return view('placeholder', ['title' => 'Websites', 'description' => 'Manage Nginx virtual hosts — Coming in Phase 3']);
    }

    public function databases()
    {
        return view('placeholder', ['title' => 'Databases', 'description' => 'Create and manage MySQL databases — Coming in Phase 3']);
    }

    public function ssl()
    {
        return view('placeholder', ['title' => 'SSL Certificates', 'description' => "Let's Encrypt SSL management — Coming in Phase 3"]);
    }

    public function files()
    {
        return view('placeholder', ['title' => 'File Manager', 'description' => 'Browse, upload, and edit server files — Coming in Phase 3']);
    }

    public function cron()
    {
        return view('placeholder', ['title' => 'Cron Jobs', 'description' => 'Schedule and manage cron jobs — Coming in Phase 3']);
    }

    public function terminal()
    {
        return view('placeholder', ['title' => 'Web Terminal', 'description' => 'Browser-based SSH terminal — Coming in Phase 3']);
    }

    public function notifications()
    {
        return view('placeholder', ['title' => 'Notifications', 'description' => 'LINE Messaging API alerts — Coming in Phase 4']);
    }
}
