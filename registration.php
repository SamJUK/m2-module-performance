<?php
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Console\CommandLocator;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'SamJUK_Performance', __DIR__);
// Registered here rather than in di.xml so the commands exist in a Docker build with no env.php.
CommandLocator::register(\SamJUK\Performance\Console\CommandList::class);
