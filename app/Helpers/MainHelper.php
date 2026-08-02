<?php

if (! function_exists('get_country_id')) {
    function get_country_id($name)
    {
        if(request('clearCache')) {
            cache()->forget('get_country_id');
        }
        return cache()->remember('get_country_id', 60, function () use ($name) {
            return \App\Models\Country::where('name', $name)->first()->toArray();
        });
    }
}

if (! function_exists('get_state_id')) {
    function get_state_id($name)
    {
        if(request('clearCache')) {
            cache()->forget('get_state_id');
        }
        return cache()->remember('get_state_id', 60, function () use ($name) {
            return \App\Models\State::where('name', $name)->first()->toArray();
        });
    }
}


if (! function_exists('get_state_name')) {
    function get_state_name($id)
    {
        if(request('clearCache')) {
            cache()->forget("get_state_name:$id");
        }
        return cache()->remember("get_state_name:$id", 60, function () use ($id) {
            return \App\Models\State::find($id);
        });
    }
}

if (! function_exists('get_country_name')) {
    function get_country_name($id)
    {
        if(request('clearCache')) {
            cache()->forget("get_country_name:$id");
        }
        return cache()->remember("get_country_name:$id", 60, function () use ($id) {
            return \App\Models\Country::find($id);
        });
    }
}
