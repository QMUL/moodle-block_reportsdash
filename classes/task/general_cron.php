<?php

namespace block_reportsdash\task;

class general_cron extends \core\task\scheduled_task
{
    public function get_name()
    {
        // Shown in admin screens
        return get_string('general_cron', 'block_reportsdash');
    }

    public function execute()
    {
        require_once(__DIR__.'/../../block_reportsdash.php');
        $b=new \block_reportsdash();
        $b->cron();
    }
}