<?php
if (!function_exists('str_starts_with')) {
 function str_starts_with($haystack,$needle){$haystack=(string)$haystack;$needle=(string)$needle;return $needle==='' || substr($haystack,0,strlen($needle))===$needle;}
}
if (!function_exists('str_contains')) {
 function str_contains($haystack,$needle){$haystack=(string)$haystack;$needle=(string)$needle;return $needle==='' || strpos($haystack,$needle)!==false;}
}
