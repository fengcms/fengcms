<?php
// Compatibility shim for old extensions that still instantiate Services_JSON.
class Services_JSON
{
    public function __construct(...$options) {}
    public function encode($value) { return json_encode($value); }
    public function decode($value) { return json_decode($value); }
}
