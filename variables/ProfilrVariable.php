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

class ProfilrVariable
{
	public function display($opts = array())
	{
		return craft()->profilr->display($opts);
	}

	public function alert($opts = array())
	{
		return craft()->profilr->alert($opts);
	}
}