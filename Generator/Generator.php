<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace GeckoLibs\DTOGeneratorBundle\Generator;

/**
 *
 * @author 255196
 */
class Generator
{

    /**
     * Saves  data into a file.
     * 
     * @param string $filename
     * @param string $content
     * @return mixed
     */
    public function saveFileContent($filename, $content)
    {
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0775, true);
        }
        
        return file_put_contents($filename, sprintf("<?php\n\n%s", $content));
    }

}
