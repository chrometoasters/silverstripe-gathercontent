<?php

// Ensure compatibility with PHP 7.2 - SilverStripe 3.6 uses Object and SilverStripe 3.7 uses SS_Object
if (!class_exists('SS_Object')) {
    class_alias('Object', 'SS_Object');
}
