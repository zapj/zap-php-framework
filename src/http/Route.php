<?php 

namespace zap\http;

class Route {


    public static function get($pattern, $action){
        return app()->router->get($pattern,$action);
    }

    public static function post($pattern, $action){
        return app()->router->post($pattern,$action);
    }

    public static function put($pattern, $action){
        return app()->router->put($pattern,$action);
    }

    public static function delete($pattern, $action){
        return app()->router->delete($pattern,$action);
    }

    public static function any($pattern, $action){
        return app()->router->any($pattern,$action);
    }

    public static function resource($prefix, $class){
        Route::get($prefix, "{$class}@index");
        Route::get($prefix, "{$class}@create");
        Route::post($prefix, "{$class}@store");
        Route::get($prefix, "{$class}@show");
        Route::get("$prefix/{id}/edit", "{$class}@edit");
        Route::put("$prefix/{id}", "{$class}@update");
        Route::delete("$prefix/{id}", "{$class}@destroy");
    }

    public static function prefix($prefix,$params){
        return app()->router->prefix($prefix,$params);
    }
}