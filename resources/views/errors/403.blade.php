@extends('errors::minimal')

@section('title', 'Prohibido')
@section('code', '403')
@section('message', $exception->getMessage() ?: 'Prohibido')
