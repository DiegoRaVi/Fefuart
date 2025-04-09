<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    
    public function listEvents(){
        $events = Event::all();
        if($events->isEmpty()){
            return response()->json(['message' => 'No events found'], 404);
        }
        return response()->json(Event::with('user')->get(), 200);
    }

    
    public function getEventsByUserId(){

        $user = Auth::user();
        $events = Event::where('user_id', $user->id)->get();

        if($events->isEmpty()){
            return response()->json(['message' => 'No events found'], 404);
        }

        return response()->json($events, 200);
    }

    
    public function addEvent(Request $request){

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
        ]);

        $event = Event::create([
            'title' => $request->title,
            'description' => $request->description,
            'date' => $request->date,
            'location' => $request->location,
            'status' => 'pending',
            'user_id' => Auth::id(),
        ]);

        return response()->json($event, 201);
    }

    
    public function getEventById($id){

        $event = Event::with('user')->find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        return response()->json($event, 200);
    }

    
    public function updateEvent(Request $request, $id){

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
        ]);
        
        $user = Auth::user();
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        
        if ($user->role !== 'admin' && $event->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        
        if ($user->role !== 'admin' && $event->status !== 'pending') {
            return response()->json(['message' => 'You can only edit pending events'], 403);
        }

        $event->update($request->only([
            'title', 'description', 'date', 'location'
        ]));

        return response()->json($event, 200);
    }

    
    public function deleteEvent($id){

        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully'], 200);
    }
}

