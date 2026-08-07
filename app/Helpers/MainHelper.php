<?php

if (! function_exists('get_country_id')) {
    function get_country_id($name)
    {
        if(request('clearCache')) {
            cache()->forget('get_country_id');
        }
        return cache()->remember('get_country_id:' . strtolower(trim($name)), 60, function () use ($name) {
            // Case-insensitive match — this is fed from free-text mobile
            // app input (e.g. "malaysia" vs "Malaysia"), and an exact
            // case-sensitive match previously crashed the whole request
            // with "Call to a member function toArray() on null" whenever
            // casing didn't line up exactly.
            $country = \App\Models\Country::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();
            return $country ? $country->toArray() : null;
        });
    }
}

if (! function_exists('get_state_id')) {
    function get_state_id($name)
    {
        if(request('clearCache')) {
            cache()->forget('get_state_id');
        }
        return cache()->remember('get_state_id:' . strtolower(trim($name)), 60, function () use ($name) {
            // See get_country_id() above — same case-insensitivity + null-safety fix.
            $state = \App\Models\State::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();
            return $state ? $state->toArray() : null;
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
