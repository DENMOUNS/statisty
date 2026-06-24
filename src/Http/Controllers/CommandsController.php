<?php
namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CommandsController extends BaseDashboardController
{
    public function index()
    {
        $allCommands = Artisan::all();
        $commandsList = [];
        foreach ($allCommands as $name => $command) {
            if (!str_contains($name, ':')) continue;
            $commandsList[] = [
                'name' => $name,
                'description' => $command->getDescription(),
            ];
        }

        return view('statisty::commands', array_merge($this->shellData('commands'), [
            'commands' => $commandsList,
        ]));
    }

    public function execute(Request $request)
    {
        $command = $request->input('command');
        if (!$command) {
            return back()->with('error', 'Command required');
        }
        
        try {
            Artisan::call($command);
            $output = Artisan::output();
            return back()->with('success', 'Command executed successfully')->with('output', $output);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
