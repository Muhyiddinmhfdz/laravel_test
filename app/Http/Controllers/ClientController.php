<?php

namespace App\Http\Controllers;

use App\Models\MyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Elegant\Sanitizer\Sanitizer;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100|unique:my_client',
            'is_project' => 'in:0,1',
            'self_capture' => 'in:0,1',
            'client_prefix' => 'required|string|max:4',
            'client_logo' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
        ]);

        $filters = [
            'name'=>'trim|escape',
            'slug'=>'trim|escape',
            'address'=>'trim|escape',
            'email'=>'trim|escape',
            'password'=>'trim|escape',
            'phone_number'=>'trim|escape',
            'city'=>'trim|escape',
        ];
        $sanitizer  = new Sanitizer($request->all(), $filters);
        $attrclean=$sanitizer->sanitize();

        
        $clientLogo = $request->file('client_logo')->store('client_logos', 's3');
        $clientLogoUrl = Storage::disk('s3')->url($clientLogo);

        $client = MyClient::create([
            'name' => $attrclean['name'],
            'slug' => $attrclean['slug'],
            'is_project' => $attrclean['is_project'] ?? '0',
            'self_capture' => $attrclean['self_capture'] ?? '1',
            'client_prefix' => $attrclean['client_prefix'],
            'client_logo' => $clientLogoUrl,
            'address' => $attrclean['address'] ?? null,
            'phone_number' => $attrclean['phone_number'] ?? null,
            'city' => $attrclean['city'] ?? null,
        ]);

        Redis::set($client->slug, json_encode($client));

        return response()->json($client, 201);
    }

    public function show($slug)
    {
        $clientData = Redis::get($slug);
        if ($clientData) {
            return response()->json(json_decode($clientData));
        }

        $client = MyClient::where('slug', $slug)->first();
        if ($client) {
            Redis::set($slug, json_encode($client));
            return response()->json($client);
        }

        return response()->json(['message' => 'Client not found'], 404);
    }

    public function update(Request $request, $slug)
    {
        $client = MyClient::where('slug', $slug)->first();
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100|unique:my_client',
            'is_project' => 'in:0,1',
            'self_capture' => 'in:0,1',
            'client_prefix' => 'required|string|max:4',
            'client_logo' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
        ]);

        $filters = [
            'name'=>'trim|escape',
            'slug'=>'trim|escape',
            'address'=>'trim|escape',
            'email'=>'trim|escape',
            'password'=>'trim|escape',
            'phone_number'=>'trim|escape',
            'city'=>'trim|escape',
        ];
        $sanitizer  = new Sanitizer($request->all(), $filters);
        $attrclean=$sanitizer->sanitize();

        if ($request->hasFile('client_logo')) {
            $clientLogo = $request->file('client_logo')->store('client_logos', 's3');
            $clientLogoUrl = Storage::disk('s3')->url($clientLogo);
            $client->client_logo = $clientLogoUrl;
        }

        $client->update($attrclean);

        Redis::del($slug);
        Redis::set($slug, json_encode($client));

        return response()->json($client);
    }

    
    public function destroy($slug)
    {
        $client = MyClient::where('slug', $slug)->first();
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $client->delete();
        Redis::del($slug);
        return response()->json(['message' => 'Client soft deleted']);
    }
}
