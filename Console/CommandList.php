<?php
declare(strict_types=1);

namespace SamJUK\Performance\Console;

use Magento\Framework\Console\CommandListInterface;

class CommandList implements CommandListInterface
{
    public function getCommands(): array
    {
        return [new PreloadBuild(), new PreloadCapture()];
    }
}
