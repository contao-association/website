<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *
 * PHP version 5
 * @copyright  Contao Association 2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    commercial
 */


class ModuleHarvestRegistration extends ModuleRegistration
{

    /**
     * Make sure a client name does not already exist. Harvest cannot handle duplicate client names.
     * @param   array
     */
    protected function createNewUser($arrMember)
    {
        $strName = Harvest::generateClientName($arrMember, Harvest::getSubscription($arrMember));
        $intId = array_search($strName, Harvest::getClientLookupTable());

        if ($intId !== false) {
            $this->Template->error = $GLOBALS['TL_LANG']['ERR']['harvestDuplicate'];
            return;
        }

        parent::createNewUser($arrMember);
    }
}
