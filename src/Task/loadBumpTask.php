<?php

namespace NordCode\RoboBump\Task;

trait loadBumpTask
{

    /**
     * @param array|string $files
     * @return Bump
     */
    public function bumpTask($files)
    {
        return new Bump($files);
    }
}
