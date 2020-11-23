<?php

// Remove style sheets from themes
unset(
    $GLOBALS['TL_DCA']['tl_theme']['list']['operations']['css'],
    $GLOBALS['TL_DCA']['tl_theme']['fields']['vars']
);
