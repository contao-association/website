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
        $objTasks = Database::getInstance()->prepare("
          SELECT * FROM  (
              SELECT t.id, t.title, t.deadline, s.status, s.progress, u.email, u.language
              FROM tl_task t
              JOIN tl_task_status s ON t.id=s.pid
              JOIN tl_user u ON s.assignedTo=u.id
              ORDER BY s.tstamp DESC
          ) as tasks
          GROUP BY tasks.id
          HAVING deadline<=? AND ((status!='completed' AND status!='declined') OR progress<100)
        ")->execute(time());

        while ($objTasks->next()) {

            // Load language file for the user
            $this->loadLanguageFile('tasks_reminder', $objTasks->language, true);

            $objEmail = new Email();
            $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
            $objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
            $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['tasks_reminder_subject'], $GLOBALS['TL_CONFIG']['websiteTitle'], $objTasks->id);
            $objEmail->text = sprintf($GLOBALS['TL_LANG']['MSC']['tasks_reminder_text'], $objTasks->title, $objTasks->id, $GLOBALS['TL_CONFIG']['websiteTitle'], $GLOBALS['TL_CONFIG']['websiteTitle']);
            $objEmail->sendTo($objTasks->email);
        }
    }
}
