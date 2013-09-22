<?php

/**
 *
 * @package     Craft Profilr
 * @version     Version 1.0
 * @author      Connor Smith
 * @copyright   Copyright (c) 2013
 * @link        sphinx.io
 *
 */

namespace Craft;

class ProfilrPlugin extends BasePlugin
{
    function getName()
    {
        return Craft::t('Craft Profilr');
    }
    
    function getVersion()
    {
        return '1.0';
    }
    
    function getDeveloper()
    {
        return 'Connor Smith';
    }
    
    function getDeveloperUrl()
    {
        return 'http://sphinx.io';
    }

    public function hasCpSection()
    {
        return false;
    }
}