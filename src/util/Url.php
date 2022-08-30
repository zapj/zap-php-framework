<?php

namespace zap\util;

use app\helpers\Auth;
use zap\http\Request;

class Url
{
    public function base($url = null){
        return app()->baseUrl($url);
    }

    public function home(){
        return app()->baseUrl();
    }

    public function current(){
        return app()->baseUrl(app()->router->currentUri . (Request::query_string() ? '?'.Request::query_string():''));
    }

    public function action($controller,$queryParams = null,$pathParams = null){
        $prefix = rtrim(app()->router->currentRoute['pattern'],'.*/');
        $baseUrl = app()->router->baseUrl . $prefix;
        [$controller,$action] = explode('@',$controller);
        $controller = strtolower(trim(preg_replace('/([A-Z])/', '-$1', $controller),'-'));
        $action = strtolower(trim(preg_replace('/([A-Z])/', '-$1', $action),'-'));
        if($action){
            $baseUrl .= '/' . $controller . '/' . $action;
        } else if($controller){
            $baseUrl .= '/' . $controller;
        }
        if(is_array($pathParams)){
            $pathParams = array_map(function($segment){return urlencode($segment);},$pathParams);
            $baseUrl .= '/' . join('/',$pathParams);
        }

        if(is_array($queryParams)){
            $baseUrl .= '?' . http_build_query($queryParams);
        }
        return $baseUrl;
    }



}