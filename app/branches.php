<?php
declare(strict_types=1);

function branch_find(array $data,int $id): ?array {
    foreach($data['branches']??[] as $branch)if((int)($branch['id']??0)===$id)return $branch;
    return null;
}
function branch_current(?array $data=null): array {
    $data=$data??db_load();$id=(int)($data['meta']['active_branch_id']??($data['_branch_context']['id']??1));
    return branch_find($data,$id)??($data['branches'][0]??['id'=>1,'name'=>'MR BAR','slug'=>'mr-bar','code'=>'MAIN']);
}
function branch_available_for_user(array $data,?array $user=null): array {
    $user=$user??(is_array($_SESSION['user']??null)?$_SESSION['user']:[]);
    return array_values(array_filter($data['branches']??[],fn($b)=>!empty($b['active'])&&db_user_can_branch($user,(int)$b['id'])));
}
function branch_public_base(): string {
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/it/'));
    if(preg_match('#^(.*?/it)(?:/|$)#',$script,$m))return rtrim((string)$m[1],'/');
    return '';
}
function branch_slug_path(array $branch,string $tail=''): string {
    $slug=rawurlencode((string)($branch['slug']??'shop'));
    $path=branch_public_base().'/shop/'.$slug.'/';
    if($tail!=='')$path.=ltrim($tail,'/');
    return $path;
}
function branch_resolve_request(array $raw): ?array {
    $slug=db_requested_branch_slug();return $slug!==''?db_branch_by_slug_raw($raw,$slug):null;
}
function branch_redirect_old_slug(array $raw): void {
    $resolved=branch_resolve_request($raw);if(!$resolved||empty($resolved['alias']))return;
    $branch=$resolved['branch'];$uri=(string)($_SERVER['REQUEST_URI']??'');$query=$_GET;unset($query['branch']);
    $tail='';
    if(preg_match('#/shop/[^/?]+/(.*?)(?:\?|$)#iu',$uri,$m))$tail=(string)$m[1];
    $target=branch_slug_path($branch,$tail);
    if($query)$target.=(strpos($target,'?')===false?'?':'&').http_build_query($query);
    header('Location: '.$target,true,301);exit;
}
function branch_switcher_html(array $data): string {
    $user=is_array($_SESSION['user']??null)?$_SESSION['user']:[];if(!$user)return '';
    $branches=branch_available_for_user($data,$user);if(!$branches)return '';
    $current=branch_current($data);$return=(string)($_SERVER['REQUEST_URI']??'admin.php');
    if(strpos($return,'//')===0||preg_match('#^[a-z]+:#i',$return))$return='admin.php';
    ob_start();?>
    <aside class="mr-branch-switcher" data-branch-switcher>
      <span class="mr-branch-kicker">ACTIVE SHOP</span>
      <button type="button" class="mr-branch-current" data-branch-toggle aria-expanded="false">
        <i><?=h(mb_strtoupper(mb_substr((string)($current['name']??'MR'),0,2,'UTF-8'),'UTF-8'))?></i>
        <span><b><?=h((string)($current['name']??'MR BAR'))?></b><small><?=h((string)($current['code']??'MAIN'))?> · เปลี่ยนร้าน</small></span><em>⌃</em>
      </button>
      <div class="mr-branch-menu" data-branch-menu hidden>
        <strong>เลือกร้านที่กำลังจัดการ</strong>
        <?php foreach($branches as $branch):?>
        <form method="post" action="branch-switch.php">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="branch_id" value="<?=(int)$branch['id']?>"><input type="hidden" name="return_to" value="<?=h($return)?>">
          <button type="submit" class="<?=((int)$branch['id']===(int)$current['id'])?'active':''?>"><span><?=h((string)$branch['name'])?></span><small>/shop/<?=h((string)$branch['slug'])?>/</small></button>
        </form>
        <?php endforeach;?>
        <?php if(!empty($user['super_admin'])):?><nav><a href="branch-manager.php">⚙ จัดการร้าน/สาขา</a><a href="portal-config.php">✦ Config Web Portal</a><a href="<?=h(branch_public_base().'/Portal')?>" target="_blank">↗ เปิด Portal กลาง</a></nav><?php endif;?>
      </div>
    </aside>
    <?php return (string)ob_get_clean();
}
