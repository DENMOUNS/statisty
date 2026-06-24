<?php
namespace Statisty\Http\Controllers;

class EventsController extends BaseDashboardController
{
    public function index()
    {
        $eventsData = [];
        
        try {
            $providers = app()->getProviders(\Illuminate\Foundation\Support\Providers\EventServiceProvider::class);
            if (!empty($providers)) {
                $provider = $providers[0];
                $reflection = new \ReflectionClass($provider);
                if ($reflection->hasProperty('listen')) {
                    $property = $reflection->getProperty('listen');
                    $property->setAccessible(true);
                    $listeners = $property->getValue($provider);
                    
                    foreach ($listeners as $event => $eventListeners) {
                        $eventsData[] = [
                            'event' => $event,
                            'listeners' => (array) $eventListeners,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}

        return view('statisty::events', array_merge($this->shellData('events'), [
            'eventsData' => $eventsData,
        ]));
    }
}
