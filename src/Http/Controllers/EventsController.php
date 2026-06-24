<?php
namespace Statisty\Http\Controllers;

class EventsController extends BaseDashboardController
{
    public function index()
    {
        return view('statisty::events', array_merge($this->shellData('events'), [
            'events' => [],
        ]));
    }
}
