<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{

    // GET EVENTO POR ID
    public function getEventById($id){

        $event = Event::with('user')->find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        return response()->json($event, 200);
    }

    // GET TODOS LOS EVENTOS
    public function getEvents(){

        $events = Event::with('user')->orderBy('status', 'desc')->get();

        if ($events->isEmpty()) {
            return response()->json(['message' => 'No events found'], 404);
        }

        return response()->json($events, 200);
    }

    // GET EVENTOS CONFIRMADOS
    public function getConfirmedEvents(){

        $events = Event::where('status', 'confirmed')->with('user')->orderBy('date', 'asc')->get();

        if ($events->isEmpty()) {
            return response()->json(['message' => 'No confirmed events found'], 404);
        }

        return response()->json($events, 200);
    }

    // GET EVENTOS PENDIENTES
    public function getPendingEvents(){

        $events = Event::where('status', 'pending')->with('user')->orderBy('date', 'asc')->get();
    
        $user = Auth::user();

        if($user->role !== "admin"){
            return response()->json(['message' => 'You are not an ADMIN'], 403);
        }
    
        if ($events->isEmpty()) {
            return response()->json(['message' => 'No pending events found'], 404);
        }

        return response()->json($events, 200);
    }

    // GET EVENTOS RECHAZADOS
    public function getRejectedEvents(){

        $events = Event::where('status', 'rejected')->with('user')->orderBy('date', 'asc')->get();

        $user = Auth::user();

        if($user->role !== "admin"){
            return response()->json(['message' => 'You are not an ADMIN'], 403);
        }
    
        if ($events->isEmpty()) {
            return response()->json(['message' => 'No rejected events found'], 404);
        }

        return response()->json($events, 200);
    }

    // GET EVENTOS HECHOS
    public function getDoneEvents(){

        $user = Auth::user();

        if($user->role !== "admin"){
            return response()->json(['message' => 'You are not an ADMIN'], 403);
        }

        $events = Event::where('status', 'done')->with('user')->orderBy('date', 'asc')->get();
    
        if ($events->isEmpty()) {
            return response()->json(['message' => 'No done events found'], 404);
        }

        return response()->json($events, 200);
    }

    // GET TODOS LOS EVENTOS DE UN USER
    public function getEventsByUser(){

        $user = Auth::user();
        $events = Event::where('user_id', $user->id)->get();

        if($events->isEmpty()){
            return response()->json(['message' => 'No events found'], 404);
        }

        return response()->json($events, 200);
    }

    // POST NUEVO EVENTO
    public function addEvent(Request $request){

        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
            'schedule' => 'required|in:morning,evening'
        ]);

        $event = Event::create([
            'title' => $request->title,
            'description' => $request->description,
            'phone' => $request->phone,
            'date' => $request->date,
            'location' => $request->location,
            'schedule' => $request->schedule,
            'status' => 'pending',
            'user_id' => $user->id
        ]);

        return response()->json($event, 201);
    }

    // UPDATE EVENTO POR ID
    /*public function updateEventById(Request $request, $id){

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
            'title', 'description', 'date', 'location', 'status'
        ]));

        return response()->json($event, 200);
    }*/

    public function updateEventById(Request $request, $id) {
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

    // ✅ Validar campos solo si NO es admin o si se actualizan más campos
    if ($user->role !== 'admin' || $request->hasAny(['title', 'date', 'location'])) {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
        ]);
    }

    // Solo actualiza los campos enviados
    $event->update($request->only([
        'title', 'description', 'date', 'location', 'status'
    ]));

    return response()->json($event, 200);
}

    // DELETE EVENTO POR ID
    public function deleteEventById($id){

        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully'], 200);
    }
}

