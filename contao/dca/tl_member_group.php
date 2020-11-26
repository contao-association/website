<?php

$GLOBALS['TL_DCA']['tl_member_group']['config']['closed'] = true;

unset(
    $GLOBALS['TL_DCA']['tl_member_group']['list']['global_operations'],
    $GLOBALS['TL_DCA']['tl_member_group']['list']['operations']['copy'],
    $GLOBALS['TL_DCA']['tl_member_group']['list']['operations']['delete'],
);
