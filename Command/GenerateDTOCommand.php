<?php

/*
 * The MIT License
 *
 * Copyright 2014 David Kalosi (david.kalosi@gmail.com).
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace GeckoLibs\DTOGeneratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GeckoLibs\DTOGeneratorBundle\Command\Helper\DialogHelper;
use GeckoLibs\DTOGeneratorBundle\Generator\DTOGenerator;

class GenerateDTOCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('geckolibs:dto:generate')
            ->setDescription('Generates a new DTO object inside a bundle')
            ->addOption('dto', null, InputOption::VALUE_REQUIRED,
                'The DTO class name to be generated')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED,
                'The properties of the newly created DTO')
            ->addOption('root', null, InputOption::VALUE_OPTIONAL,
                'The root directory for DTO classes, defaults to Model', 'Model')
            ->addOption('with-assembler', null, InputOption::VALUE_NONE,
                'Weather a Assembler class skeleton shoud be generated')
            ->setHelp(<<<EOT
The <info>geckolibs:generate:dto</info> task generates a new DTO class inside a bundle:

<info>php app/console geckolibs:generate:dto --dto=AcmeBlogBundle:Blog/PostDTO</info>

The above command would create an empty DTO in the following
namespace <info>Acme\BlogBundle\Model\Blog\PostDTO</info>.

To change the root directory of the DTO objects you can specify a
<comment>--root</comment> option:
                
<info>php app/console geckolibs:generate:dto --dto=AcmeBlogBundle:Blog/PostDTO --root=TransportObjects</info>
                
The above command would create an empty DTO in the following
namespace <info>Acme\BlogBundle\TransportObjects\Blog\PostDTO</info>.
                
You can also optionally specify the properties you want to generate in the new
DTO:

<info>php app/console geckolibs:generate:dto --dto=AcmeBlogBundle:Blog/PostDTO --properties="name:string createdAt:\DateTime"</info>

The command can also generate an empty Assembler class
<comment>--with-assembler</comment> option:

<info>php app/console doctrine:generate:dto --dto=AcmeBlogBundle:Blog/PostDTO --with-assembler</info>

To deactivate the interaction mode, simply use the `--no-interaction` option
without forgetting to pass all needed options:

<info>php app/console doctrine:generate:dto --dto=AcmeBlogBundle:Blog/PostDTO  --properties="name:string createdAt:\DateTime" --with-assembler --no-interaction</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $properties = $this->parseFields($input->getOption('properties'));
        $input->setOption('properties', $properties);

        //    if ($input->isInteractive()) {
        //   
        //   }

        list($bundleName, $dto) = $this->parseShortcutNotation($input->getOption('dto'));

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundleName);
        $fileName = join(DIRECTORY_SEPARATOR,
            array($bundle->getPath(),
            $input->getOption('root'),
            str_replace('\\', DIRECTORY_SEPARATOR, $dto) . '.php'));

        if (file_exists($fileName)) {
            $output->writeln(sprintf('<bg=red>DTO "%s:%s" already exists</>.',
                    $bundleName, $dto));
            throw new \InvalidArgumentException();
        }

        $this->printSummary($input, $output);

        $generator = new DTOGenerator();
        $generator->generate($bundle, $dto, $input->getOption('root'),
            $input->getOption('properties'), $input->getOption('with-assembler'));
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Welcome to the GeckoLibs DTO generator');

        $output->writeln(array(
            '',
            'This command helps you generate Data Transfer Objects (DTOs).',
            '',
            'First, you need to specify the root directory for the objects',
            'Then specify the DTO name in shortcut notation like <comment>AcmeBlogBundle:PostDTO</comment>.',
            ''
        ));

        $output->writeln('');
        $root = $dialog->ask(
            $output,
            $dialog->getQuestion('Enter the root directory',
                $input->getOption('root')), $input->getOption('root')
        );

        $input->setOption('root', $root);

        $bundleNames = array_keys($this->getContainer()->get('kernel')->getBundles());

        while (true) {
            $dto = $dialog->askAndValidate($output,
                $dialog->getQuestion('Enter the the DTO name',
                    $input->getOption('dto')),
                array('GeckoLibs\DTOGeneratorBundle\Command\Validators', 'validateDTOName'),
                false, $input->getOption('dto'), $bundleNames);

            list($bundle, $dto) = $this->parseShortcutNotation($dto);

            try {
                $b = $this->getContainer()->get('kernel')->getBundle($bundle);
                $fileName = join(DIRECTORY_SEPARATOR,
                    array($b->getPath(),
                    $input->getOption('root'),
                    str_replace('\\', DIRECTORY_SEPARATOR, $dto) . '.php'));

                if (!file_exists($fileName)) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>DTO "%s:%s" already exists</>.',
                        $bundle, $dto));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>',
                        $bundle));
            }
        }

        $input->setOption('dto', $bundle . ':' . $dto);

        // class location
        // fields
        $input->setOption('properties',
            $this->addFields($input, $output, $dialog));


        // assembler?
        $output->writeln('');
        $withAssembler = $dialog->askConfirmation($output,
            $dialog->getQuestion('Do you want to generate a Skeleton Assembler class',
                $input->getOption('with-assembler') ? 'yes' : 'no', '?'),
            $input->getOption('with-assembler'));
        $input->setOption('with-assembler', $withAssembler);
    }

    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog || !($dialog instanceof GeckoLibs\DTOGeneratorBundle\Command\Helper\DialogHelper)) {
            $this->getHelperSet()->set($dialog = new DialogHelper());
        }

        return $dialog;
    }

    protected function parseShortcutNotation($shortcut)
    {
        $entity = str_replace('/', '\\', $shortcut);

        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The DTO name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/PostDTO)',
                $entity));
        }

        return array(substr($entity, 0, $pos), substr($entity, $pos + 1));
    }

    private function printSummary(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('DTO class name ' . $input->getOption('dto'));

        if ($input->getOption('with-assembler')) {
            $output->writeln('Will generate skeleton assembler');
        }
        $output->writeln('');

        $table = $this->getHelper('table');
        $table->setHeaders(array('Property Name', 'Property Type'));

        $rows = array();
        foreach ($input->getOption('properties') as $name => $type) {
            $rows[] = array($name, $type);
        }

        $table->setRows($rows);
        $output->writeln('');
        $table->render($output);
        $output->writeln('');
    }

    private function addFields(InputInterface $input, OutputInterface $output,
        DialogHelper $dialog)
    {
        $fields = $this->parseFields($input->getOption('properties'));
        $output->writeln(array(
            '',
            'Start adding properties to the DTO',
            '',
        ));

        $fieldValidator = function ($type) {
            return $type;
        };

        while (true) {
            $output->writeln('');
            //  $generator = $this->getGenerator();
            $fieldName = $dialog->askAndValidate($output,
                $dialog->getQuestion('New property name (press <return> to stop adding properties)',
                    null),
                function ($name) use ($fields) {

                if (isset($fields[$name])) {
                    throw new \InvalidArgumentException(sprintf('Property "%s" is already defined.',
                        $name));
                }

                return $name;
            });
            if (!$fieldName) {
                break;
            }

            $defaultType = 'string';

            // try to guess the type by the column name prefix/suffix
            if (strtolower(substr($fieldName, -2)) == 'at') {
                $defaultType = '\DateTime';
            } elseif (strtolower(substr($fieldName, -2)) == 'id') {
                $defaultType = 'integer';
            } elseif (strtolower(substr($fieldName, 0, 2)) == 'is') {
                $defaultType = 'boolean';
            } elseif (strtolower(substr($fieldName, 0, 3)) == 'has') {
                $defaultType = 'boolean';
            }

            $type = $dialog->askAndValidate($output,
                $dialog->getQuestion('Property type', $defaultType),
                $fieldValidator, false, $defaultType);

            $fields[$fieldName] = $type;
        }

        return $fields;
    }

    private function parseFields($input)
    {
        if (is_array($input)) {
            return $input;
        }

        $fields = array();
        foreach (explode(' ', $input) as $value) {
            if (!strlen($value)) {
                continue;
            }

            if (!strpos($value, ':')) {
                throw new \InvalidArgumentException('Properties must be entered in the format type:field');
            }

            list($type, $field) = explode(':', $value);
            $fields[$field] = $type;
        }

        return $fields;
    }

}
