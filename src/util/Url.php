<?php

namespace zap\util;

use app\helpers\Auth;
use zap\http\Request;

class Url
{
    public function base($url = null,$fullPath = false){
        return $fullPath ? config('config.site_url','') . app()->baseUrl($url) : app()->baseUrl($url);
    }

    public function home(){
        $prefix = rtrim(app()->router->currentRoute['pattern'],'.*/');
        $baseUrl = app()->router->baseUrl . $prefix;
        return $baseUrl;
    }

    public function current(){
        return app()->baseUrl(app()->router->currentUri . (Request::query_string() ? '?'.Request::query_string():''));
    }

    public function route($format,$args = []){
        return app()->router->baseUrl . Str::format($format,$args);
    }

    public function active($action,$output = 'active'){
        $currentAction = app()->dispatcher->controller . (app()->dispatcher->method ? '/' . app()->dispatcher->method : '');
        if(app()->_currentAction){
            $currentAction = app()->_currentAction;
        }

        if(is_string($action) && ($action == $currentAction || preg_match("#^{$action}$#i",$currentAction))){
            echo $output;
            return $output ? null : true ;
        }else if(in_array($currentAction, $action)){
            echo $output;
            return $output ? null : true ;
        }
        return false;
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
        if(is_array($queryParams) && count($queryParams) >0){
            $querystring = http_build_query($queryParams);
            $baseUrl .= $querystring ? '?' . $querystring:'';
        }
        return $baseUrl;
    }



}