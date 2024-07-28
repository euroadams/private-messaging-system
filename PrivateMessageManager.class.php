<?php


    class PrivateMessageManager {            
        
        use GenericClass;

        private static $dbTableName = 'private_messages';
                
        /*** Constructor ***/
        public function __construct(){
                       
            
        }
        
        
    

        /*** Destructor ***/
        public function __destruct(){
                    
        
        }
        
	
        
           
        /*** Method for logging private message into database ***/
        public function send($senderId, $recId, $msgSubj, $msg){
            
            $stat = true;
            !$senderId? ($senderId = 0) : '';
            
            if($recId && $msgSubj && $msg){	
                
                $sql = "INSERT INTO self::$dbTableName (MESSAGE_SUBJECT, INBOX, SENDER_ID, USER_ID, TIME) VALUES(?,?,?,?,NOW())";
                $valArr = array($msgSubj, $msg, $senderId, $recId);
                $stat = $this->DBM->doSecuredQuery($sql, $valArr);
            
            }
            
            return $stat;
            
        }
        
            
        /*** Method for handling pm message display ***/
        public function renderHandler($metaArr){

            $data=$mssgnum=$pageHead=$isInbox=$isOldInbox="";
            
            global $GLOBAL_siteName, $GLOBAL_page_self, $GLOBAL_rdr,
            $GLOBAL_notLogged;		
            
            $siteName =  $GLOBAL_siteName;
            $pageSelf = $GLOBAL_page_self;
            
            $isSentPm =  false;
            $type =  strtolower($this->ENGINE->get_assoc_arr($metaArr, 'type'));	
            $backDoor =  strtolower($this->ENGINE->get_assoc_arr($metaArr, 'backDoor'));
            $uid = strtolower($this->ENGINE->get_assoc_arr($metaArr, 'uid'));
            $uid = $uid? $uid : $this->ACCOUNT->SESS->getUserId();		
            $countSessNew = strtolower($this->ENGINE->get_assoc_arr($metaArr, 'countSessNew'));	
            $U = $this->ACCOUNT->loadUser($uid);
            $username = $U->getUsername();
            $userId = $U->getUserId();

            $pmCheckBoxAlert = '<div class="total-pm-checked"></div>';
            
            /***************************BEGIN URL CONTROLLER****************************/
            if(!$countSessNew){

                $full_path =  $this->ENGINE->get_page_path('page_url', '', true);
                $path_arr = explode("/", $full_path);
                
                $pathKeysArr = $backDoor? array('pageUrl', 'user', 'event', "pageId") : array('pageUrl', "pageId");
                $maxPath = $backDoor? 4 : 2;	
                
                $this->ENGINE->url_controller(array('pathKeys'=>$pathKeysArr, 'maxPath'=>$maxPath));

            }
            /*******************************END URL CONTROLLER***************************/	
            
            
            
            if($username){
                
                switch($type){

                    case 'old-inbox': $typeCol = 'OLD_INBOX'; $isOldInbox = true; $typeUrl = $backDoor? 'user-events/'.$username.'/old-inbox' : 'old-inbox'; 
                            $emptyUrl = '/delete-pm/old-inbox'; $emptyUrlTxt = 'delete old messages'; 
                            $delSelUrl = '/delete-pm/selected-old-pm';  $pageHead = 'Old Messages';
                            break;

                    case 'sent-pm': $typeCol = ''; $isSentPm = true; $typeUrl = $backDoor? 'user-events/'.$username.'/sent-pm' : 'sent-pm'; 
                            $emptyUrl = ''; $emptyUrlTxt = ''; 
                            $delSelUrl = '';  $pageHead = 'Sent PM';
                            break;

                    default: $typeCol = 'INBOX'; $isInbox = true; $typeUrl = $backDoor? 'user-events/'.$username.'/inbox' : 'inbox';
                            $emptyUrl = '/delete-pm/clear-inbox'; $emptyUrlTxt = 'clear inbox'; 
                            $delSelUrl = '/delete-pm/selected-pm'; $pageHead = 'Inbox';

                        
                }

                $pageHead = ($backDoor? $username.' => ' : 'My ').$pageHead;
                
                $countSubQry = $isSentPm? '' : ($countSessNew? 'READ_STATUS !=1' : $typeCol.' !=""');
                $cnd = $isSentPm? 'SENDER_ID=?' : 'USER_ID=? AND '.$countSubQry;

                ///////////PDO QUERY///////////
                $sql = "SELECT COUNT(*) AS TOTAL_RECS FROM self::$dbTableName WHERE (".$cnd.")";
                $valArr = array($userId);
                $totalPm = $this->DBM->doSecuredQuery($sql, $valArr)->fetchColumn();
            
                if($countSessNew)
                    return $totalPm;
                
                //////DECIDE OLD MESSAGES/////////

                ///////////PDO QUERY///////////
                $sql = "UPDATE self::$dbTableName SET OLD_INBOX = INBOX, INBOX = '' WHERE (USER_ID=? AND INBOX != '' AND READ_STATUS = 1 AND TIME >= (TIME + INTERVAL 1 WEEK))";
                $valArr = array($userId);
                $stmt = $this->DBM->doSecuredQuery($sql, $valArr);	

                /////////////////RESET ALL CHECKS NOT EXECUTED//////////////////
                ///////////PDO QUERY///////////
                $sql = "UPDATE self::$dbTableName SET SELECTION_STATUS=0 WHERE USER_ID=? AND SELECTION_STATUS !=0";
                $valArr = array($userId);
                $stmt = $this->DBM->doSecuredQuery($sql, $valArr);
                
                if(isset($isInbox) && !$backDoor){

                    /////////////UPDATE READ STATUS IN DB//////
                    ///////////PDO QUERY///////////
                    $sql = "UPDATE self::$dbTableName SET READ_STATUS=1 WHERE USER_ID=? AND READ_STATUS !=1";
                    $valArr = array($userId);
                    $stmt = $this->DBM->doSecuredQuery($sql, $valArr);

                }
                if($totalPm){
                    
                    $totalRecords = $totalPm;

                    /**********CREATE THE PAGINATION*******/			
                    $pageUrl  = $typeUrl;
                    $paginationArr = $this->paginationHandler(array('totalRec'=>$totalRecords,'url'=>$pageUrl,'extendLast'=>true));
                    $pagination = $paginationArr["pagination"];
                    $totalPage = $paginationArr["totalPage"];
                    $perPage = $paginationArr["perPage"];
                    $startIndex = $paginationArr["startIndex"];
                    $pageId = $paginationArr["pageId"];

                    ///////////////END OF PAGINATION/////////////

                    ///////////PDO QUERY///////////
                    $sql = "SELECT * FROM self::$dbTableName WHERE ".$cnd." ORDER BY TIME DESC LIMIT ".$startIndex.",".$perPage;
                    $valArr = array($userId);
                    $stmt = $this->DBM->doSecuredQuery($sql, $valArr);	
                        
                    while($row = $this->DBM->fetchRow($stmt)){
                        
                        $pmId = $row["ID"];
                        $pmSender = $this->ACCOUNT->memberIdToggle($row["SENDER_ID"]);
                        $pmReceiver = $this->ACCOUNT->memberIdToggle($row["USER_ID"]);

                        if(!$pmSender)
                            $pmSender = '';

                        $pmSubject = $this->bbcHandler('', array('action' => 'decode', 'content' => $row["MESSAGE_SUBJECT"]));								
                        $pmContent = $this->bbcHandler('', array('action' => 'decode', 'content' => ($isSentPm? ($row[$K="INBOX"]? $row[$K] : $row["OLD_INBOX"]) : $row[$typeCol])));				
                        $timeSent = $row["TIME"];

                        ///FORMAT THE WAY DATES ARE SHOWN///////
                    
                        $pmDate = $this->ENGINE->time_ago($timeSent);
                        $pmReplyLink = '<a role="button" href="/pm/reply/'.$pmId.'" class="pull-r btn btn-success">Reply</a>';
                        $profileSanMetaArr = array('anchor'=>true, 'cls'=>'prime bg-white');
                        
                        $data .= '<div class="pm-base">
                                    <div class="base-rad base-b-pad clear">
                                        <header class="pm-sender clear">sent '.($isSentPm? 'to' : 'by').': <b>'.($isSentPm? $this->ACCOUNT->sanitizeUserSlug($pmReceiver, $profileSanMetaArr) : ($pmSender? $this->ACCOUNT->sanitizeUserSlug($pmSender, $profileSanMetaArr) : "webmaster")).'</b> ('.$pmDate.')  '.(($pmSender && !$backDoor && !$isSentPm)? $pmReplyLink : '').'</header>
                                        <div class="pm-subject" title="'.$pmId.'"><span class="green">Subject:</span> <span class="prime">'.$pmSubject.'</span></div>					
                                        <div class="pm-contents">'.($backDoor || $isSentPm? '' : '<label><input title="check this message for delete" type="checkbox" data-pm="'.$pmId.'" data-name="delchk" class="checkbox_inbox checkbox" /></label>').'<div class="pm-content-ctrl align-l">'.nl2br($pmContent).'</div></div>'.(($pmSender && !$backDoor && !$isSentPm)? '<div class="base-pad">'.$pmReplyLink.'</div>' : '').'
                                    </div>
                                </div>';	
                                        
                                    
                    }
                    
                    if(!$isSentPm){
                        
                        $checkAll =  '<li><a class="links" href="/pm-blacklist">pm blacklist</a></li><li><a href="/'.$typeUrl.'" class="links pm-check-all" >check all</a></li>';
                        
                        function getToggle($type, $url, $boxUnqId, $metaArr){
                    
                            !$type? ($type = 'del-all') : '';

                            list($pmCheckBoxAlert, $emptyUrlTxt, $username) = $metaArr;
                            
                            switch(strtolower($type)){
                                
                                case 'select': 
                                
                                    $link = '<li><a href="'.$url.'" class="links" data-toggle="smartToggler" data-id-targets="'.$boxUnqId.'" >delete selected</a></li>';
                                    
                                    $cfm = '<div id="'.$boxUnqId.'" class="red modal-drop hide has-close-btn">
                                                '.$pmCheckBoxAlert.'
                                                <p>You are about to delete the highlighted messages</p>
                                                <p>Please confirm</p>
                                                <a href="'.$url.'" class="btn btn-danger" role="button" >delete selected</a>
                                                <button class="btn close-toggle">close</button>
                                            </div>';
                                            
                                    break;
                                    
                                default:
                                
                                    $link = '<li><a href="'.$url.'"  class="links" data-toggle="smartToggler" data-id-targets="'.$boxUnqId.'" >'.$emptyUrlTxt.'</a></li>';
                                    
                                    $cfm = '<div id="'.$boxUnqId.'" class="hide" ><div class="alert alert-danger">
                                                <b/> 
                                                    WARNING!!!<hr/>'. strtoupper($username).'<br/><br/> 
                                                    you are about to delete all your '.((stripos($emptyUrlTxt, 'old') !== false)? 'old' : '').' inbox messages 
                                                    <br/><br/>please confirm<br/><br/>NOTE: you will no longer be able to access them once deleted<br/>
                                                    <input type="button"  class="btn btn-danger empty-inbox" value="OK" data-landpage="'.$url.'" /> 
                                                    <input class="btn close-toggle" type="button" value="CLOSE" />
                                                </b>
                                            </div></div>';
                                    
                                
                                
                            }
                                    
                                    
                            return array($link, $cfm);

                        }
                        
                        $metaArr = array($pmCheckBoxAlert, $emptyUrlTxt, $username);
                        
                        list($deleteSelectedLink, $delSelCfm) = getToggle($K='select', $delSelUrl, 'del-select-top-cfm', $metaArr);			
                        list($deleteSelectedLinkBtm, $delSelCfmBtm) = getToggle($K, $delSelUrl, 'del-select-btm-cfm', $metaArr);
                        list($emptyLink, $emptyLinkCfm) = getToggle($K='', $emptyUrl, 'del-all-top-cfm', $metaArr);			
                        list($emptyLinkBtm, $emptyLinkCfmBtm) = getToggle($K, $emptyUrl, 'del-all-btm-cfm', $metaArr);	
                    
                    }
                    
                }else
                    $data = '<span class="alert alert-danger">sorry '.(!$backDoor? 'you have' : $username.' has').' no '.($isSentPm? 'sent pm yet' : ($isInbox? 'new messages in '.(!$backDoor? 'your' : '').' inbox' : 'old messages')).'</span>';
                                
                $linkTo =  ($isSentPm? '' : '<li><a class="links" href="/sent-pm">view sent pm</a></li>').'<li><a class="links" href="/'.($isInbox? 'old-inbox' : 'inbox').'">'.($isInbox? 'view older messages' : 'view inbox').'</a></li>';
                

            }else
                $notLogged = $GLOBAL_notLogged;
            

            $this->buildPageHtml(array("pageTitle"=>$pageHead,	
                    "preBodyMetas"=>$this->getNavBreadcrumbs('<li><a href="/'.$pageSelf.'" title="">'.$pageHead.'</a></li>'),
                    "pageBody"=>'
                    <div class="single-base blend">
                        <div class="base-ctrl">'.
                            (isset($notLogged)? $notLogged : '').
                            ($username? '
                            <div class="panel panel-mine-1">									
                                <h1 class="panel-head page-title">'.strtoupper($pageHead).' '.(isset($totalPm)? '<span class="small">('.$totalPm.')</span>' : '').'</h1>					
                                <div class="panel-body pm-root">
                                    <h2>'.(isset($pagination)? '(Page <span class="cyan">'.$pageId.'</span> of '.$totalPage.')' : '').'</h2>'.
                                    (isset($pagination)?  $pagination : '').'
                                    <div id="deletedselection"></div>'.
                                    (!$backDoor? '
                                        <nav class="nav-base no-pad">
                                            <ul class="nav nav-pills justified-center">'.
                                                (isset($checkAll)? $checkAll : '').
                                                (isset($deleteSelectedLink)? $deleteSelectedLink : '').
                                                (isset($linkTo)? $linkTo : '').
                                                (isset($emptyLink)? $emptyLink : '').'
                                            </ul>
                                        </nav>'.(isset($delSelCfm)? $delSelCfm : '').(isset($emptyLinkCfm)? $emptyLinkCfm : '') : ''
                                    ).$pmCheckBoxAlert.$this->ENGINE->get_global_var('ss', "SESS_ALERT").(isset($data)? $data : '').
                                    (isset($pagination)? $pagination : '').
                                    (!$backDoor? '
                                        <nav class="nav-base no-pad">
                                            <ul class="nav nav-pills justified-center">'.
                                                (isset($checkAll)? $checkAll : '').
                                                (isset($deleteSelectedLinkBtm)? $deleteSelectedLinkBtm : '').
                                                (isset($linkTo)? $linkTo : '').
                                                (isset($emptyLinkBtm)? $emptyLinkBtm : '').'
                                            </ul>
                                        </nav>'.(isset($delSelCfmBtm)? $delSelCfmBtm : '').(isset($emptyLinkCfmBtm)? $emptyLinkCfmBtm : '') : ''
                                    ).'
                                    '.$pmCheckBoxAlert.'
                                </div>
                            </div>' : '').'
                        </div>
                    </div>'
            ));
            
        }

        
        
            
        /*** Method for handling private message blacklist ***/
        public function blacklistHandler($meta){
            
            global $GLOBAL_isAdmin, $GLOBAL_notLogged, $GLOBAL_sessionUrl, $GLOBAL_sessionUrl_unOnly,
            $GLOBAL_page_self_rel, $rdr;
            
            $table = 'pm_blacklists';
            $userId = $this->ENGINE->is_assoc_key_set($meta, $K='uid')? $this->ENGINE->get_assoc_arr($meta, $K) : $this->ACCOUNT->SESS->getUserId();
            $blacklistUserId = $this->ENGINE->get_assoc_arr($meta, 'buid');
            $U = $this->ACCOUNT->loadUser($blacklistUserId);
            $blacklistUsername = $U->getUsername();
            $blacklistIsStaff = $U->isStaff();
            $action = strtolower($this->ENGINE->get_assoc_arr($meta, 'action'));
            $pageUrl  = $this->ENGINE->get_page_path('page_url', 1);
            $isAjax = $this->ENGINE->is_ajax();
            $backBtn = $isAjax? '' : $this->getBackBtn();
            //$rdr? '' : ($rdr = '/pm-blacklist');
            $acc=$alert=$totalRecords='';

            $add = 'add'; 
            $remove = 'remove'; 
            $clear = 'clear';
            $check = 'check';
            
            switch($action){
            
                case $add:
                case $remove:
                case $clear:
                case $check:
                    $add = ($action == $add);
                    $remove = ($action == $remove);
                    $clear = ($action == $clear);
                    $check = ($action == $check);
            
                    if(!$clear && !$blacklistUsername){

                        $alert = '<span class="alert alert-warning">Sorry no valid data was specified '.$backBtn.'<span>';
                        
                        ///AJAX RELOAD EXIT////	
                        if($isAjax){		
                                        
                            $res['res'] = $alert;
                            echo json_encode($res);
                            exit();

                        }
                    
                        return $alert;

                    }
            
                    $sql = "SELECT STATE FROM ".$table." WHERE (USER_ID=? AND BLACKLISTED_USER_ID=?) LIMIT 1";
                    $valArr = array($userId, $blacklistUserId);
                    $state = $this->DBM->doSecuredQuery($sql, $valArr, true)->fetchColumn();
                    $found = $this->DBM->getRecordCount();
            
                    if($check)
                        return $state;
            
                    if($blacklistUserId != $userId){

                        if(!$blacklistIsStaff || !$add || $GLOBAL_isAdmin){
                
                            if($found || $clear){
                
                                $valArr = array($userId);
                                $clear? '' : ($valArr[] = $blacklistUserId) ;
                                $sql = "UPDATE ".$table." SET STATE = ".($add? 1  : 0)." WHERE USER_ID=? ".($clear? '' : 'AND BLACKLISTED_USER_ID=? LIMIT 1');
                                $stmt = $this->DBM->doSecuredQuery($sql, $valArr);
                
                            }elseif($add){
                
                                $sql = "INSERT INTO ".$table." (USER_ID, BLACKLISTED_USER_ID, STATE, TIME) VALUES(?,?, 1, NOW())";
                                $valArr = array($userId, $blacklistUserId);
                                $stmt = $this->DBM->doSecuredQuery($sql, $valArr);
                
                            }
                
                            $blacklistedUserUrl = $this->ACCOUNT->sanitizeUserSlug($blacklistUsername, array('anchor'=>true));
                
                            if($remove && !$state){
                
                                $alert = 'The user: '.$blacklistedUserUrl.' was not found on your blacklist. '.$backBtn;
                
                            }else{
                
                                $alert = $clear? 'Your pm blacklist '.($found? 'has been' : 'is already').' emptied' : 
                                $GLOBAL_sessionUrl.($add? ' added ' : ' removed ').$blacklistedUserUrl.($add? ($state? ' already' : '').' to ' : ' from ').'your <a class="links" href="/pm-blacklist">pm blacklist</a>';
                
                            }

                            $alertCls = ($state? 'danger' : 'success');
                
                        }else{

                            $alert = 'Sorry '.$GLOBAL_sessionUrl.' you cannot blacklist a staff. '.$backBtn;
                            $alertCls = 'danger';

                        }
                    
                    }else{

                        $alert = 'Sorry '.$GLOBAL_sessionUrl.' cannot blacklist yourself. '.$backBtn;
                        $alertCls = 'danger';

                    }
                    
                    $alert = '<span class="alert alert-'.$alertCls.'">'.$alert.'<span>'; 
                            
                    ///AJAX RELOAD EXIT////	
                    if($isAjax){		
                                    
                        $res['res'] = $alert;
                        echo json_encode($res);
                        exit();

                    }
                    
                    $this->ENGINE->set_global_var('ss', 'SESS_ALERT', $alert);
                    
                    if($rdr){

                        header("Location:".$rdr."#prof-pmb");
                        exit();

                    }
                    
                    return $alert; break;
                    
                default:
                    if($this->ACCOUNT->SESS->getUsername()){
                        
                        ///////////PDO QUERY///////////
                        $sql = "SELECT COUNT(*) FROM ".$table." WHERE USER_ID=? AND STATE=1 ";
                        $valArr = array($userId);
                        $totalRecords = $this->DBM->doSecuredQuery($sql, $valArr)->fetchColumn();
                
                        /**********CREATE THE PAGINATION*******/			
                        $paginationArr = $this->paginationHandler(array('totalRec'=>$totalRecords, 'url'=>$pageUrl));
                        $pagination = $paginationArr["pagination"];
                        $totalPage = $paginationArr["totalPage"];
                        $perPage = $paginationArr["perPage"];
                        $startIndex = $paginationArr["startIndex"];
                        $pageId = $paginationArr["pageId"];
                        
                        $sql = "SELECT * FROM ".$table." WHERE USER_ID=? AND STATE=1 ORDER BY TIME DESC LIMIT ".$startIndex.",".$perPage;
                        $valArr = array($userId);
                        $stmt = $this->DBM->doSecuredQuery($sql, $valArr);		
                            
                        while($row = $this->DBM->fetchRow($stmt)){
            
                            $acc .= $this->ACCOUNT->getUserVcard($row["BLACKLISTED_USER_ID"], array('time'=>$row["TIME"],
                            'append'=>'<a role="button" '.$this->runByAjax(array('reloadUrl' => $GLOBAL_page_self_rel)).' class="btn btn-primary btn-xs" href="/pm-blacklist/remove/'.$this->ACCOUNT->memberIdToggle($row["BLACKLISTED_USER_ID"]).'" title="Remove from blacklist">remove</a>'));
            
                        }
                        
                        $acc = $acc? '<div class="hr-dividers">'.$acc.'</div>' :
                            '<span class="alert alert-danger">'.$GLOBAL_sessionUrl.' have not added anyone to your pm blacklist yet</span>';
            
                    }else{
            
                        $notLogged = $GLOBAL_notLogged;
            
                    }
                    
                    $getToggle = '(<a href="/inbox" class="links" >view inbox</a>'.($totalRecords? ' | <a title="Clear your pm blacklist" href="'.($K='/pm-blacklist/clear').'" class="links" data-toggle="smartToggler" >remove all blacklisted users</a>' : '').')
                                <div class="hide alert alert-warning has-close-btn">
                                    <p>You are about to clear your pm blacklist<br/>Please confirm</p>
                                    <a role="button" title="Clear your pm blacklist" href="'.$K.'" class="links clear_pmb btn btn-danger" >Remove All</a>
                                    <button class="btn close-toggle">Close</button>
                                </div>';
                        
                    $pageTitle	= 'PM Blacklist';
                                    
                    $ajaxReloadableContent = 
                    (isset($notLogged)? $notLogged : 
                        '<h1 class="panel-head page-title">'.$pageTitle.'</h1>'.
                        ((isset($pageId) && $pageId)? '<div class="cpop">(<span class="cyan">'.$pageId.'</span> of '.$totalPage.')</div>' : '').'									
                        <div class="panel-body sides-padless" >									
                            <div class="" >
                                <span class="black"> You have blacklisted (<span class="cyan">'.$totalRecords.'</span>) person'.($totalRecords > 1? 's' : '').' from sending you private messages<hr/>
                            </div>'.$getToggle.$pagination.'
                            <div class="">
                                <div class="inline-form-group hr-divider bd-inverse">'
                                    .
                                    $this->getSearchForm(array('url' => ''.$GLOBAL_page_self_rel, 'fieldName' => 'blacklist_uid', 
                                    /*'pageResetUrl' => $pageUrl,*/ 'fieldLabel' => 'Add To Blacklist',  'formAttr' => $this->runByAjax(array('reloadUrl' => $GLOBAL_page_self_rel)),
                                    'fieldPH' => 'username', 'btnName' => 'blacklist', 'btnLabel' => 'Add'))
                                    .
                                '</div>'
                                .$acc.
                            '</div>'.
                            $pagination.$getToggle.'	
                        </div>'
                    );
                            
                    ///AJAX RELOAD EXIT////	
                    if($this->ENGINE->is_ajax()){		
                                    
                        $res['res'] = $ajaxReloadableContent;
                        echo json_encode($res);
                        exit();

                    }
                    
                    $this->buildPageHtml(array("pageTitle"=>$pageTitle,
                                "preBodyMetas"=>$this->getNavBreadcrumbs('<li><a href="'.$GLOBAL_page_self_rel.'" title="">'.$pageTitle.'</a></li>'),
                                "pageBody"=>'										
                                <div class="single-base blend">
                                    <div class="base-ctrl">
                                        <div class="panel panel-limex" data-ajax-rel-rcv="">
                                            '.$ajaxReloadableContent.'										
                                        </div>
                                    </div>
                                </div>'
                    ));
            
            }
            
        }



        
        /**Method for handling PM delete**/
        public function deleteHandler($meta){
            
            $table = self::$dbTableName;
            
            /***************************BEGIN URL CONTROLLER****************************/

            if(isset($pagePathArr[1]) && strtolower($pagePathArr[1]) == "clear-inbox"){
            
                $pathKeysArr = array('pageUrl', 'tab');
                $maxPath = 2;
                $rdrLnk = '<a href="/inbox" class="links" >back to inbox</a>';
                $returnUrl = 'inbox';
                $sql = "DELETE FROM ".$table." WHERE USER_ID=? AND INBOX != ''";
            
            }elseif(isset($pagePathArr[1]) && strtolower($pagePathArr[1]) == "old-pm"){
            
                $pathKeysArr = array('pageUrl', 'tab');
                $maxPath = 2;	
                $returnUrl = 'old-inbox';
                $rdrLnk = '<a href="/old-inbox" class="links" >back to old inbox</a>';
                $sql = "DELETE FROM ".$table." WHERE USER_ID=? AND OLD_INBOX != ''";
            
            }elseif(isset($pagePathArr[1]) && strtolower($pagePathArr[1]) == "selected-pm"){
            
                $pathKeysArr = array('pageUrl', 'tab');
                $maxPath = 2;
                $returnUrl = 'inbox';
                $rdrLnk = '<a href="/inbox" class="links" >back to inbox</a>';
                $sql = "DELETE FROM ".$table." WHERE USER_ID=? AND SELECTION_STATUS=1 ";
            
            }elseif(isset($pagePathArr[1]) && strtolower($pagePathArr[1]) == "selected-old-pm"){
            
                $pathKeysArr = array('pageUrl', 'tab');
                $maxPath = 2;	
                $returnUrl = 'old-inbox';
                $rdrLnk = '<a href="/old-inbox" class="links" >back to old inbox</a>';
                $sql = "DELETE FROM ".$table." WHERE USER_ID=? AND SELECTION_STATUS=1 ";
            
            }else{
            
                $pathKeysArr = array();
                $maxPath = 0;
            
            }

            $ENGINE->url_controller(array('pathKeys'=>$pathKeysArr, 'maxPath'=>$maxPath));

            /*******************************END URL CONTROLLER***************************/

            if($sessUsername){	
                
                ///////////PDO QUERY////		
                
                $valArr = array($sessUid);
                $stmt = $dbm->doSecuredQuery($sql, $valArr, true);
                $resCount = $dbm->getRecordCount();				

                $data = '<span class="alert alert-success">('.$resCount.') message'.(($resCount > 1)? 's' : '').' has been deleted successfully </span>';
                $ENGINE->set_global_var('ss', 'SESS_ALERT', $data);
            
            }else
                $notLogged = $GLOBAL_notLogged;
            
            
            header("Location:/".$returnUrl);
            exit();
                
        }
        
        
        
        
        
        /**Method for PM delete checkbox action**/
        public function deleteCheckboxHandler($meta){			

            if(isset($_POST[$K="pm"]) || isset($_GET[$K])){
                    
                $data=$checkstatus=$row=$checked=$message="";					
                
                if(isset($_POST[$K]))
                    $pmIds = $_POST[$K];				
                
                elseif(isset($_GET[$K]))
                    $pmIds = $_GET[$K];
                
                
                $pmIdsArr = explode(',', trim($pmIds, ','));
                $placeHolders = trim(str_repeat('?,', count($pmIdsArr)), ',');
                
                /////////////GET CHECK_STATUS FROM DB///////
                
                /////PDO QUERY////////

                $sql = "SELECT SELECTION_STATUS FROM self::$dbTableName WHERE USER_ID = ? AND ID IN (".$placeHolders.")";
                $valArr = array_merge(array($sessUid), $pmIdsArr);
                $checkStatus = $dbm->doSecuredQuery($sql, $valArr)->fetchColumn();
                
                $checkStatus = $checkStatus? 0 : 1;

                ///////EXECUTE THE CHECKING ACCORDINGLY IN THE DB/////////
                ///////////PDO QUERY///////					
                $sql = "UPDATE self::$dbTableName SET SELECTION_STATUS=? WHERE USER_ID = ? AND ID IN (".$placeHolders.")";
                $valArr = array_merge(array($checkStatus, $sessUid), $pmIdsArr);
                $stmt = $dbm->doSecuredQuery($sql, $valArr);
                
                ////////GET TOTAL NUMBER OF MESSAGES CHECKED FOR DELETE//////
                    ///////////PDO QUERY///////					
                $sql = "SELECT COUNT(*) FROM self::$dbTableName WHERE USER_ID = ? AND SELECTION_STATUS=1 ";
                $valArr = array($sessUid);
                $recordCount = $dbm->doSecuredQuery($sql, $valArr)->fetchColumn();	
                
                $message = $recordCount? '(<span class="red">'.$recordCount.'</span>) message'.(($recordCount > 1)? 's' : '').' checked for delete' : '';

                $message = '<div class="black"><b>'.$message.'</b></div>';
                
                echo $message;

            }

            if(!$GLOBAL_isAjax){

                header("Location:".$rdrAlt);
                exit();

            }

        }
		
	
        
        



    }


















?>