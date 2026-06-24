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

    public function store(\Illuminate\Http\Request $request)
    {
        $command = $request->input('command');
        $frequency = $request->input('frequency');

        if (!$command || !$frequency) {
            return back()->with('error', 'Commande et fréquence requises.');
        }

        $consolePath = base_path('routes/console.php');
        if (!file_exists($consolePath)) {
            return back()->with('error', 'Le fichier routes/console.php est introuvable.');
        }

        $code = "\nuse Illuminate\Support\Facades\Schedule;\nSchedule::command('{$command}')->{$frequency}();\n";
        
        try {
            file_put_contents($consolePath, $code, FILE_APPEND);
            return back()->with('success', 'Le schedule a été injecté avec succès dans routes/console.php !');
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur d\'écriture : ' . $e->getMessage());
        }
    }
}
