<?php
/** Backward-compatible PR preview API backed by the current MR BAR TIME Staff Preview. */
require_once __DIR__.'/staff-preview.php';
if(!function_exists('pr_preview_requested')){
function pr_preview_requested(): bool {return staff_preview_requested();}
function pr_preview_context(): ?array {
    $ctx=staff_preview_context();if(!$ctx)return null;
    if(($ctx['expected_role']??'')!=='pr' || empty($ctx['pr'])){http_response_code(400);exit('พนักงานที่เลือกไม่ใช่ PR');}
    $GLOBALS['MRBAR_PR_PREVIEW_CONTEXT']=$ctx;return $ctx;
}
function pr_preview_is_active(): bool {return staff_preview_is_active() && (($GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT']['expected_role']??'')==='pr');}
function pr_preview_params(): array {return staff_preview_params();}
function pr_preview_url(string $path): string {return staff_preview_url($path);}
function pr_preview_block_post(): void {staff_preview_block_post();}
function pr_preview_banner(): string {return staff_preview_banner();}
}
