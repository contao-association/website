<?php

/**
 * tasks_reminder extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-tasks_reminder
 */

class TasksReminder extends System
{

    public function __construct()
    {
        parent::__construct();
    }

    public function sendReminders()
    {
        $this->loadLanguageFile('default');

        $objTasks = Database::getInstance()->prepare("SELECT * FROM tl_task WHERE deadline<=?")
                                           ->execute(time());

        while ($objTasks->next()) {
            $objStatus = Database::getInstance()->prepare("SELECT *, (SELECT email FROM tl_user WHERE tl_user.id=tl_task_status.assignedTo) AS email FROM tl_task_status WHERE pid=? ORDER BY tstamp DESC")
                                                ->limit(1)
                                                ->execute($objTasks->id);

            if (!$objStatus->numRows || $objStatus->status == 'completed') {
                continue;
            }

            $objEmail = new Email();
            $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
            $objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
            $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['tasks_reminder_subject'], $GLOBALS['TL_CONFIG']['websiteTitle'], $objTasks->id);
            $objEmail->text = sprintf($GLOBALS['TL_LANG']['MSC']['tasks_reminder_text'], $objTasks->title, $objTasks->id, $GLOBALS['TL_CONFIG']['websiteTitle'], $GLOBALS['TL_CONFIG']['websiteTitle']);
            $objEmail->sendTo($objStatus->email);
        }
    }
}
