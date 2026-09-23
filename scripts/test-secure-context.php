<?php
declare(strict_types=1);

require __DIR__.'/../app/secure-context.php';

function assert_secure(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

assert_secure(mrbar_request_is_https(['HTTPS'=>'on']),'HTTPS=on must be secure');
assert_secure(mrbar_request_is_https(['SERVER_PORT'=>'443']),'port 443 must be secure');
assert_secure(mrbar_request_is_https(['HTTP_X_FORWARDED_PROTO'=>'https, http']),'forwarded HTTPS must be secure');
assert_secure(mrbar_request_is_https(['HTTP_X_FORWARDED_SSL'=>'on']),'forwarded SSL must be secure');
assert_secure(!mrbar_request_is_https(['HTTPS'=>'off','SERVER_PORT'=>'80']),'HTTP must not be secure');
assert_secure(mrbar_admin_preview_requested(['admin_staff_preview'=>'1']),'staff preview flag must be detected');
assert_secure(mrbar_admin_preview_requested(['admin_pr_preview'=>'1']),'PR preview flag must be detected');
assert_secure(!mrbar_admin_preview_requested(['admin_staff_preview'=>'0']),'disabled preview must not be detected');
assert_secure(mrbar_safe_request_host(['HTTP_HOST'=>'mrbarsupport.com'])==='mrbarsupport.com','valid host must be kept');
assert_secure(mrbar_safe_request_host(['HTTP_HOST'=>"evil.test\r\nX-Test: 1"])==='mrbarsupport.com','invalid host must fall back');
assert_secure(mrbar_safe_request_uri('/time.php',['REQUEST_URI'=>'//evil.test/path'])==='/time.php','network-path URI must fall back');
assert_secure(mrbar_force_staff_https_enabled(['HTTP_HOST'=>'mrbarsupport.com']),'production must use HTTPS by default');
assert_secure(mrbar_force_staff_https_enabled(['HTTP_HOST'=>'www.mrbarsupport.com']),'www must use HTTPS by default');
assert_secure(!mrbar_force_staff_https_enabled(['HTTP_HOST'=>'localhost']),'non-production hosts must keep their configured protocol');
assert_secure(!mrbar_force_staff_https_enabled(['HTTP_HOST'=>'mrbarsupport.com','MRBAR_FORCE_STAFF_HTTPS'=>'0']),'explicit HTTPS override may disable redirect');
assert_secure(mrbar_force_staff_https_enabled(['MRBAR_FORCE_STAFF_HTTPS'=>'1']),'HTTPS redirect accepts explicit 1');
assert_secure(mrbar_force_staff_https_enabled(['MRBAR_FORCE_STAFF_HTTPS'=>'on']),'HTTPS redirect accepts on');

echo "secure context tests passed\n";
