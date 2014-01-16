<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace GeckoLibs\DTOGeneratorBundle\Command;

class Validators
{

    public static function validateDTOName($entity)
    {
        if (false === strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/PostDTO)',
                    $entity));
        }

        if (!preg_match('/^(.*)DTO$/', $entity)) {
            throw new \InvalidArgumentException(sprintf('The name must be suffixed with DTO ("%s" given, expecting something like AcmeBlogBundle:Blog/PostDTO)',
                    $entity));
        }

        return $entity;
    }

}
