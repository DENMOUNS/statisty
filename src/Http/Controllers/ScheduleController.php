<?php
namespace Statisty\Http\Controllers;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleController extends BaseDashboardController
{
    public function index()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events())->map(function ($event) {
            return [
                'command' => $event->command,
                'expression' => $event->expression,
                'description' => $event->description,
                'timezone' => $event->timezone,
            ];
        });

        return view('statisty::schedule', array_merge($this->shellData('schedule'), [
            'events' => $events,
        ]));
    }
}
