<?php

/*
 * Copyright 2010 Pablo Díez Pascual <pablodip@gmail.com>
 *
 * This file is part of MondongoBundle.
 *
 * MondongoBundle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * MondongoBuneld is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with MondongoBundle. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Bundle\MondongoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Mondongo\Mondator\Mondator;

/**
 * GenerateCommand.
 *
 * @package MondongoBundle
 * @author  Pablo Díez Pascual <pablodip@gmail.com>
 */
class GenerateCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('mondongo:generate')
            ->setDescription('Generate documents classes from config classes.')
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('processing config classes');

        $configClasses = array();
        foreach ($this->container->getKernelService()->getBundles() as $bundle) {
            $bundleClass     = get_class($bundle);
            $bundleNamespace = substr($bundleClass, 0, strrpos($bundleClass, '\\'));

            if (is_dir($dir = $bundle->getPath().'/Resources/config/mondongo')) {
                $finder = new Finder();
                foreach ($finder->files()->name('*.yml')->followLinks()->in($dir) as $file) {
                    foreach ((array) Yaml::load($file) as $class => $configClass) {
                        // class
                        if (0 === strpos($class, $bundleNamespace)) {
                            if (
                                0 !== strpos($class, $bundleNamespace.'\\Document')
                                ||
                                strlen($bundleNamespace.'\\Document') !== strrpos($class, '\\')
                            ) {
                                throw new \RuntimeException(sprintf('The class "%s" is not in the Document namespace of the bundle.', $class));
                            }
                        }

                        // outputs
                        if (0 === strpos($class, $bundleNamespace)) {
                            if (!isset($configClass['document_output'])) {
                                $configClass['document_output'] = $bundle->getPath().'/Document';
                            }
                            if (!isset($configClass['repository_output'])) {
                                $configClass['repository_output'] = $bundle->getPath().'/Repository';
                            }
                        } else {
                            unset($configClass['document_output'], $configClass['repository_output']);
                        }

                        // merge
                        if (!isset($configClasses[$class])) {
                            $configClasses[$class] = array();
                        }
                        $configClasses[$class] = array_merge_recursive($configClasses[$class], $configClass);
                    }
                }
            }
        }

        $output->writeln('generating classes');

        $mondator = new Mondator();
        $mondator->setConfigClasses($configClasses);
        $mondator->setExtensions(array(
            new \Mondongo\Extension\Core(),
        ));
        $mondator->process();
    }
}