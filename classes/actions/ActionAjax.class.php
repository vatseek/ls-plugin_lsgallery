<?php

class PluginLsgallery_ActionAjax extends ActionPlugin
{

    /**
     * @var ModuleUser_EntityUser
     */
    protected $oUserCurrent = null;

    /**
     * Action initiaization
     */
    public function Init()
    {
        $this->oUserCurrent = $this->User_GetUserCurrent();
        $this->Viewer_SetResponseAjax('json');
    }

    /**
     * Register routes
     */
    protected function RegisterEvent()
    {
        $this->AddEvent('upload', 'EventUpload');
        $this->AddEvent('deleteimage', 'EventDeleteImage');

        $this->AddEvent('favourite', 'EventFavouriteImage');
        $this->AddEvent('vote', 'EventVoteImage');

        $this->AddEvent('setimagedescription', 'EventSetImageDescription');
        $this->AddEvent('setimagetags', 'EventSetImageTags');
        $this->AddEvent('markascover', 'EventSetImageAsCover');

        $this->AddEvent('markfriend', 'EventSetImageUser');
        $this->AddEvent('changemark', 'EventChangeMark');
        $this->AddEvent('removemark', 'EventRemoveMark');

        $this->AddEvent('getrandomimages', 'EventGetRandomImages');

        $this->AddEvent('getnewimages', 'EventGetNewImages');
        $this->AddEvent('getbestimages', 'EventGetBestImages');

        $this->AddEvent('autocompleteimagetag', 'EventAutocompeleteImageTags');
        $this->AddEvent('autocompletefriend', 'EventAutocompeleteFriend');
    }

    /**
     * AJAX загрузка фоток
     *
     * @return unknown
     */
    protected function EventUpload()
    {
        // В зависимости от типа загрузчика устанавливается тип ответа
        if (getRequest('is_iframe')) {
            $this->Viewer_SetResponseAjax('jsonIframe', false);
        } else {
            $this->Viewer_SetResponseAjax('json');
        }


        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return Router::Action('error');
        }

        if (!isset($_FILES['Filedata']['tmp_name'])) {
            $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return false;
        }

        $iAlbumId = getRequest('album_id');
        $sTargetId = null;
        $iCountPhotos = 0;

        /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
        $oAlbum = $this->PluginLsgallery_Album_GetAlbumById($iAlbumId);
        if (!$oAlbum || !$this->ACL_AllowAdminAlbumImages($this->oUserCurrent, $oAlbum)) {
            $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return false;
        }
        $iCountPhotos = $oAlbum->getImageCount();

        /**
         * Максимальное количество фото в album
         */
        if ($iCountPhotos >= Config::Get('plugin.lsgallery.count_image_max')) {
            $this->Message_AddError($this->Lang_Get('lsgallery_images_too_much_images', array('MAX' => Config::Get('plugin.lsgallery.count_image_max'))), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Максимальный размер фото
         */
        if (filesize($_FILES['Filedata']['tmp_name']) > Config::Get('plugin.lsgallery.image_max_size') * 1024) {
            $this->Message_AddError($this->Lang_Get('lsgallery_images_error_bad_filesize', array('MAX' => Config::Get('module.topic.photoset.photo_max_size'))), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Загружаем файл
         */
        $sFile = $this->PluginLsgallery_Image_UploadImage($_FILES['Filedata']);
        if ($sFile) {
            $oImage = new PluginLsgallery_ModuleImage_EntityImage();
            $oImage->setUserId($this->oUserCurrent->getId());
            $oImage->setAlbumId($oAlbum->getId());
            $oImage->setFilename($sFile);

            if ($oImage = $this->PluginLsgallery_Image_AddImage($oImage)) {
                $oAlbum = $this->PluginLsgallery_Album_GetAlbumById($iAlbumId);
                $oAlbum->setImageCount($oAlbum->getImageCount() + 1);
                $this->PluginLsgallery_Album_UpdateAlbum($oAlbum);

                $this->Viewer_AssignAjax('file', $oImage->getWebPath('100crop'));
                $this->Viewer_AssignAjax('id', $oImage->getId());
                $this->Message_AddNotice($this->Lang_Get('lsgallery_image_added'), $this->Lang_Get('attention'));
            } else {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            }
        } else {
            $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
        }
    }

    /**
     * AJAX удаление фото
     *
     */
    protected function EventDeleteImage()
    {
        $this->Viewer_SetResponseAjax('json');

        /**
         * Проверяем авторизован ли юзер
         */
        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return Router::Action('error');
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        $oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('id'));
        if ($oImage) {
            /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
            $oAlbum = $this->PluginLsgallery_Album_GetAlbumById($oImage->getAlbumId());
            if (!$oAlbum || !$this->ACL_AllowAdminAlbumImages($this->oUserCurrent, $oAlbum)) {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return false;
            }
            $this->PluginLsgallery_Image_DeleteImage($oImage);
            //@todo перерасчет альбома, некст-прев
//            $oAlbum = $this->PluginLsgallery_Album_RegroupAlbum($oAlbum);
            $this->Message_AddNotice($this->Lang_Get('lsgallery_image_deleted'), $this->Lang_Get('attention'));
            return;
        }
        $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
    }

    public function EventSetImageDescription()
    {
        $this->Viewer_SetResponseAjax('json');

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        $oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('id'));
        if ($oImage) {
            /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
            $oAlbum = $this->PluginLsgallery_Album_GetAlbumById($oImage->getAlbumId());
            if (!$oAlbum || !$this->ACL_AllowAdminAlbumImages($this->oUserCurrent, $oAlbum)) {
                $this->Message_AddError($this->Lang_Get('no_access'), $this->Lang_Get('error'));
                return false;
            }

            $oImage->setDescription(htmlspecialchars(getRequest('text')));
            $this->PluginLsgallery_Image_UpdateImage($oImage);
        }
    }

    public function EventSetImageAsCover()
    {
        $this->Viewer_SetResponseAjax('json');

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        $oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('id'));
        if ($oImage) {
            /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
            $oAlbum = $this->PluginLsgallery_Album_GetAlbumById($oImage->getAlbumId());
            if (!$oAlbum || !$this->ACL_AllowAdminAlbumImages($this->oUserCurrent, $oAlbum)) {
                $this->Message_AddError($this->Lang_Get('no_access'), $this->Lang_Get('error'));
                return false;
            }

            $oAlbum->setCoverId($oImage->getId());
            $this->PluginLsgallery_Album_UpdateAlbum($oAlbum);
        }
    }

    public function EventSetImageTags()
    {
        $this->Viewer_SetResponseAjax('json');

        if (!func_check(getRequest('tags', null, 'post'), 'text', 2, 250)) {
            return;
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        $oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('id'));
        if ($oImage) {
            /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
            $oAlbum = $this->PluginLsgallery_Album_GetAlbumById($oImage->getAlbumId());
            if (!$oAlbum || !$this->ACL_AllowAdminAlbumImages($this->oUserCurrent, $oAlbum)) {
                $this->Message_AddError($this->Lang_Get('no_access'), $this->Lang_Get('error'));
                return false;
            }

            $oImage->setImageTags(htmlspecialchars(getRequest('tags')));
            $this->PluginLsgallery_Image_UpdateImage($oImage);
        }
    }

    public function EventAutocompeleteImageTags()
    {
        if (!($sValue = getRequest('value', null, 'post'))) {
            return;
        }

        $aItems = array();
        $aTags = $this->PluginLsgallery_Image_GetImageTagsByLike($sValue, 10);
        foreach ($aTags as $oTag) {
            $aItems[] = $oTag->getText();
        }
        $this->Viewer_AssignAjax('aItems', $aItems);
    }

    protected function EventFavouriteImage()
    {
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        $iType = getRequest('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        if (!$oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('idImage', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
        if (!$oAlbum = $this->PluginLsgallery_Album_GetAlbumById($oImage->getAlbumId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        if (!$this->ACL_AllowViewAlbumImages($this->oUserCurrent, $oAlbum)) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return;
        }

        $oFavouriteImage = $this->PluginLsgallery_Image_GetFavouriteImage($oImage->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteImage && $iType) {

            $oFavouriteImageNew = Engine::GetEntity('Favourite', array(
                        'target_id' => $oImage->getId(),
                        'user_id' => $this->oUserCurrent->getId(),
                        'target_type' => 'image',
                        'target_publish' => true
                            )
            );
            if ($this->Favourite_AddFavourite($oFavouriteImageNew)) {
                $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_image_favourite_add_ok'), $this->Lang_Get('attention'));
                $this->Viewer_AssignAjax('bState', true);
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
        if (!$oFavouriteImage && !$iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_favourite_add_no'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteImage && $iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_favourite_add_already'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteImage && !$iType) {
            if ($this->Favourite_DeleteFavourite($oFavouriteImage)) {
                $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_image_favourite_del_ok'), $this->Lang_Get('attention'));
                $this->Viewer_AssignAjax('bState', false);
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
    }

    public function EventVoteImage()
    {
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        if (!$oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('idImage', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oAlbum PluginLsgallery_ModuleAlbum_EntityAlbum */
        if (!$oAlbum = $this->PluginLsgallery_Album_GetAlbumById($oImage->getAlbumId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        if (!$this->ACL_AllowViewAlbumImages($this->oUserCurrent, $oAlbum)) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return;
        }

        if ($oImage->getUserId() == $this->oUserCurrent->getId()) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_vote_error_self'), $this->Lang_Get('attention'));
            return;
        }

        if ($oImageVote = $this->Vote_GetVote($oImage->getId(), 'image', $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_vote_error_already'), $this->Lang_Get('attention'));
            return;
        }

        if (strtotime($oImage->getDateAdd()) <= time() - Config::Get('acl.vote.image.limit_time')) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_vote_error_time'), $this->Lang_Get('attention'));
            return;
        }

        $iValue = getRequest('value', null, 'post');
        if (!in_array($iValue, array('1', '-1', '0'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('attention'));
            return;
        }

        if (!$this->oUserCurrent->getRating() >= Config::Get('acl.vote.image.rating') && $iValue) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_vote_error_acl'), $this->Lang_Get('attention'));
            return;
        }

        $oImageVote = Engine::GetEntity('Vote');
        $oImageVote->setTargetId($oImage->getId());
        $oImageVote->setTargetType('image');
        $oImageVote->setVoterId($this->oUserCurrent->getId());
        $oImageVote->setDirection($iValue);
        $oImageVote->setDate(date("Y-m-d H:i:s"));
        $iVal = 0;
        if ($iValue != 0) {
            $iVal = (float) $this->Rating_VoteImage($this->oUserCurrent, $oImage, $iValue);
        }
        $oImageVote->setValue($iVal);
        $oImage->setCountVote($oImage->getCountVote() + 1);
        if ($this->Vote_AddVote($oImageVote) && $this->PluginLsgallery_Image_UpdateImage($oImage)) {
            if ($iValue) {
                $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_image_vote_ok'), $this->Lang_Get('attention'));
            } else {
                $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_image_vote_ok_abstain'), $this->Lang_Get('attention'));
            }
            $this->Viewer_AssignAjax('iRating', $oImage->getRating());
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
    }

    protected function EventGetRandomImages()
    {
        $aRandomImages = $this->PluginLsgallery_Image_GetRandomImages(Config::Get('plugin.lsgallery.images_random'));
        $sHtml = '';
        foreach ($aRandomImages as $oImage) {
            $sHtml .= '<li><a href="' . $oImage->getUrlFull() . '"><img src="' . $oImage->getWebPath('100crop') . '" alt="Image" /></a></li>';
        }

        $this->Viewer_AssignAjax('sHtml', $sHtml);
    }

    public function EventGetNewImages()
    {
        $aResult = $this->PluginLsgallery_Image_GetImagesNew(1, Config::Get('plugin.lsgallery.image_row'));
        if ($aResult['count']) {
            $aImages = $aResult['collection'];
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('aImages', $aImages);
            $oViewer->Assign('sType', 'new');
            $sTextResult = $oViewer->Fetch(Plugin::GetTemplatePath('lsgallery') . "block.stream_photo.tpl");
            $this->Viewer_AssignAjax('sText', $sTextResult);
        }
    }

    public function EventGetBestImages()
    {
        $aResult = $this->PluginLsgallery_Image_GetImagesBest(1, Config::Get('plugin.lsgallery.image_row'));
        if ($aResult['count']) {
            $aImages = $aResult['collection'];
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('aImages', $aImages);
            $oViewer->Assign('sType', 'best');
            $sTextResult = $oViewer->Fetch(Plugin::GetTemplatePath('lsgallery') . "block.stream_photo.tpl");
            $this->Viewer_AssignAjax('sText', $sTextResult);
        }
    }

    public function EventSetImageUser()
    {
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        if (!$oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('idImage', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_not_found'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oUserMarked ModuleUser_EntityUser */
        if (!$oUserMarked = $this->User_GetUserByLogin(getRequest('login', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('user_not_found', array('login' => getRequest('login', null, 'post'))), $this->Lang_Get('error'));
            return;
        }

        if ($oImageUser = $this->PluginLsgallery_Image_GetImageUser($oUserMarked->getId(), $oImage->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_already_mark_friend'), $this->Lang_Get('error'));
            return;
        }
        if (!$this->ACL_AllowAddUserToImage($this->oUserCurrent, $oUserMarked)) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_disallow_mark_friend'), $this->Lang_Get('error'));
            return;
        }

        $aSelection = getRequest('selection');

        $oImageUser = new PluginLsgallery_ModuleImage_EntityImageUser();
        $oImageUser->setImageId($oImage->getId());
        $oImageUser->setUserId($this->oUserCurrent->getId());
        $oImageUser->setTargertUserId($oUserMarked->getId());
        $oImageUser->setLassoX($aSelection['x1']);
        $oImageUser->setLassoY($aSelection['y1']);
        $oImageUser->setLassoH($aSelection['height']);
        $oImageUser->setLassoW($aSelection['width']);
        if ($oUserMarked->getId() == $this->oUserCurrent->getId()) {
            $oImageUser->setStatus(PluginLsgallery_ModuleImage_EntityImageUser::STATUS_CONFIRMED);
        } else {
            $oImageUser->setStatus(PluginLsgallery_ModuleImage_EntityImageUser::STATUS_NEW);
        }

        if ($this->PluginLsgallery_Image_AddImageUser($oImageUser)) {
            
            if ($oUserMarked->getId() != $this->oUserCurrent->getId()) {
                $this->Notify_Send($oUserMarked, 'notify.marked.tpl', $this->Lang_Get('lsgallery_marked_subject'), array('oUser' => $this->oUserCurrent, 'oImage' => $oImage), 'lsgallery');
            }
            $this->Viewer_AssignAjax('sPath', $oUserMarked->getUserWebPath());
            $this->Viewer_AssignAjax('idUser', $oUserMarked->getId());
            $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_friend_marked'), $this->Lang_Get('attention'));
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
        }
    }

    public function EventAutocompeleteFriend()
    {
        if (!($sValue = getRequest('value', null, 'post'))) {
            return;
        }

        if (!$this->oUserCurrent) {
            return;
        }

        $aItems = array();
        $aUsers = $this->User_GetFriendsByLoginLike($sValue, 10);
        foreach ($aUsers as $oUser) {
            $aItems[] = $oUser->getLogin();
        }
        $this->Viewer_AssignAjax('aItems', $aItems);
    }

    public function EventChangeMark()
    {
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        if (!$oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('idImage', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_not_found'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oUserMarked ModuleUser_EntityUser */
        if (!$oUserMarked = $this->User_GetUserById(getRequest('idUser', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('user_not_found_by_id', array('id' => getRequest('idUser', null, 'post'))), $this->Lang_Get('error'));
            return;
        }

        if ($oUserMarked->getId() != $this->oUserCurrent->getId()) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oImageUser PluginLsgallery_ModuleImage_EntityImageUser */
        if (!$oImageUser = $this->PluginLsgallery_Image_GetImageUser($oUserMarked->getId(), $oImage->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        $sStatus = getRequest('status', null, 'post');

        if ($sStatus == PluginLsgallery_ModuleImage_EntityImageUser::STATUS_CONFIRMED || $sStatus == PluginLsgallery_ModuleImage_EntityImageUser::STATUS_DECLINED) {
            $oImageUser->setStatus($sStatus);
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        if ($this->PluginLsgallery_Image_ChangeStatusImageUser($oImageUser)) {
            $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_marked_changed_' . $sStatus), $this->Lang_Get('attention'));
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
        }
    }
    
    public function EventRemoveMark()
    {
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        /* @var $oImage PluginLsgallery_ModuleImage_EntityImage */
        if (!$oImage = $this->PluginLsgallery_Image_GetImageById(getRequest('idImage', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('lsgallery_image_not_found'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oUserMarked ModuleUser_EntityUser */
        if (!$oUserMarked = $this->User_GetUserById(getRequest('idUser', null, 'post'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('user_not_found_by_id', array('id' => getRequest('idUser', null, 'post'))), $this->Lang_Get('error'));
            return;
        }
        if (($this->oUserCurrent->getId() != $oUserMarked->getId()) && ($oImage->getUserId() != $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return;
        }
        /* @var $oImageUser PluginLsgallery_ModuleImage_EntityImageUser */
        if (!$oImageUser = $this->PluginLsgallery_Image_GetImageUser($oUserMarked->getId(), $oImage->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }


        if ($this->PluginLsgallery_Image_DeleteImageUser($oImageUser)) {
            $this->Message_AddNoticeSingle($this->Lang_Get('lsgallery_mark_removed'), $this->Lang_Get('attention'));
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
        }
    }

}