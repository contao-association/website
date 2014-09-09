<?php

/**
 * member_log extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-member_log
 */

class MemberLogFormat extends Controller
{

    /**
     * Import the Database object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Database');
    }


    /**
     * Format date according to the system config
     * @param   int
     * @return  string
     */
    public function date($intTstamp)
    {
        return $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $intTstamp);
    }


    /**
     * Format time according to the system config
     * @param   int
     * @return  string
     */
    public function time($intTstamp)
    {
        return $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $intTstamp);
    }


    /**
     * Format date & time according to the system config
     * @param   int
     * @return  string
     */
    public function datim($intTstamp)
    {
        return $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $intTstamp);
    }

    /**
     * Get field label based on DCA config
     * @param   string
     * @param   string
     */
    public function dcaLabel($strTable, $strField)
    {
        $this->loadLanguageFile($strTable);
        $this->loadDataContainer($strTable);

        if (!empty($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['label'])) {
            $strLabel = is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['label'][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['label'];
        } else {
            $strLabel = is_array($GLOBALS['TL_LANG']['MSC'][$strField]) ? $GLOBALS['TL_LANG']['MSC'][$strField][0] : $GLOBALS['TL_LANG']['MSC'][$strField];
        }

        if ($strLabel == '') {
            $strLabel = $strField;
        }

        return $strLabel;
    }


    /**
     * Format DCA field value according to Contao Core standard
     * @param   string
     * @param   string
     * @param   mixed
     * @return  string
     */
    public function dcaValue($strTable, $strField, $varValue)
    {
        $varValue = deserialize($varValue);

        $this->loadLanguageFile($strTable);
        $this->loadDataContainer($strTable);

        // Get field value
        if (strlen($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['foreignKey'])) {
            $chunks = explode('.', $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['foreignKey']);
            $varValue = empty($varValue) ? array(0) : $varValue;
            $objKey = $this->Database->execute("SELECT " . $chunks[1] . " AS value FROM " . $chunks[0] . " WHERE id IN (" . implode(',', array_map('intval', (array) $varValue)) . ")");

            return implode(', ', $objKey->fetchEach('value'));

        } elseif (is_array($varValue)) {
            foreach ($varValue as $kk => $vv) {
                $varValue[$kk] = $this->dcaValue($strTable, $strField, $vv);
            }

            return implode(', ', $varValue);

        } elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['rgxp'] == 'date') {
            return $this->date($varValue);

        } elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['rgxp'] == 'time') {
            return $this->time($varValue);

        } elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['rgxp'] == 'datim' || in_array($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['flag'], array(5, 6, 7, 8, 9, 10)) || $strField == 'tstamp') {
            return $this->datim($varValue);

        } elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['multiple']) {
            return strlen($varValue) ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];

        } elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['inputType'] == 'textarea' && ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['allowHtml'] || $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['preserveTags'])) {
            return specialchars($varValue);

        } elseif (is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['reference'])) {
            return isset($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['reference'][$varValue]) ? ((is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['reference'][$varValue])) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['reference'][$varValue][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['reference'][$varValue]) : $varValue;

        } elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['isAssociative'] || array_is_assoc($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['options'])) {
            return isset($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['options'][$varValue]) ? ((is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['options'][$varValue])) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['options'][$varValue][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['options'][$varValue]) : $varValue;
        }

        return $varValue;
    }
}
