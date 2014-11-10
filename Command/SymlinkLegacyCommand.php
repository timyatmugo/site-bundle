<?php

namespace Netgen\Bundle\MoreBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Netgen\Bundle\MoreBundle\NetgenMoreProjectBundleInterface;
use Symfony\Component\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use DirectoryIterator;

class SymlinkLegacyCommand extends SymlinkCommand
{
    /**
     * The list of folders available in standard distribution of eZ Publish Legacy
     *
     * @var array
     */
    protected $legacyDistFolders = array(
        'autoload',
        'benchmarks',
        'bin',
        'cronjobs',
        'design',
        'doc',
        'extension',
        'kernel',
        'lib',
        'schemas',
        'settings',
        'share',
        'support',
        'templates',
        'tests',
        'update',
        'var'
    );

    /**
     * Configures the command
     */
    protected function configure()
    {
        $this->addOption( 'force', null, InputOption::VALUE_NONE, 'If set, it will destroy existing symlinks before recreating them' );
        $this->setDescription( 'Symlinks legacy siteaccesses and various other legacy files to their proper locations' );
        $this->setName( 'ngmore:symlink:legacy' );
    }

    /**
     * Runs the command
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $this->forceSymlinks = (bool)$input->getOption( 'force' );
        $this->environment = $this->getContainer()->get( 'kernel' )->getEnvironment();
        $this->fileSystem = $this->getContainer()->get( 'filesystem' );

        $legacyExtensions = array();

        $kernel = $this->getContainer()->get( 'kernel' );
        foreach ( $kernel->getBundles() as $bundle )
        {
            if ( !$bundle instanceof NetgenMoreProjectBundleInterface )
            {
                continue;
            }

            if ( !$this->fileSystem->exists( $bundle->getPath() . '/ezpublish_legacy/' ) )
            {
                return;
            }

            foreach ( new DirectoryIterator( $bundle->getPath() . '/ezpublish_legacy/' ) as $item )
            {
                if ( !$item->isDir() || $item->isDot() )
                {
                    continue;
                }

                if ( !$this->fileSystem->exists( $item->getPathname() . '/extension.xml' ) )
                {
                    continue;
                }

                $legacyExtensions[] = $item->getPathname();
            }
        }

        foreach ( $legacyExtensions as $legacyExtension )
        {
            $this->symlinkLegacyExtensionSiteAccesses( $legacyExtension, $input, $output );
            $this->symlinkLegacyExtensionOverride( $legacyExtension, $input, $output );
            $this->symlinkLegacyExtensionFiles( $legacyExtension, $input, $output );
        }
    }

    /**
     * Symlinks siteccesses from a legacy extension
     *
     * @param string $legacyExtensionPath
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function symlinkLegacyExtensionSiteAccesses( $legacyExtensionPath, InputInterface $input, OutputInterface $output )
    {
        $legacyRootDir = $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' );

        if ( !$this->fileSystem->exists( $legacyExtensionPath . '/root_' . $this->environment . '/settings/siteaccess/' ) )
        {
            return;
        }

        foreach ( new DirectoryIterator( $legacyExtensionPath . '/root_' . $this->environment . '/settings/siteaccess/' ) as $item )
        {
            if ( !$item->isDir() || $item->isDot() )
            {
                continue;
            }

            $siteAccessDestination = $legacyRootDir . '/settings/siteaccess/' . $item->getBasename();

            $this->verifyAndSymlinkDirectory( $item->getPathname(), $siteAccessDestination, $output );
        }
    }

    /**
     * Symlinks override folder from a legacy extension
     *
     * @param string $legacyExtensionPath
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function symlinkLegacyExtensionOverride( $legacyExtensionPath, InputInterface $input, OutputInterface $output )
    {
        $legacyRootDir = $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' );

        $sourceFolder = $legacyExtensionPath . '/root_' . $this->environment . '/settings/override';
        if ( !$this->fileSystem->exists( $sourceFolder ) || !is_dir( $sourceFolder ) )
        {
            return;
        }

        $this->verifyAndSymlinkDirectory( $sourceFolder, $legacyRootDir . '/settings/override', $output );
    }

    /**
     * Symlinks files from a legacy extension
     *
     * @param string $legacyExtensionPath
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function symlinkLegacyExtensionFiles( $legacyExtensionPath, InputInterface $input, OutputInterface $output )
    {
        /** @var \DirectoryIterator[] $directories */
        $directories = array();

        $path = $legacyExtensionPath . '/root/';
        if ( $this->fileSystem->exists( $path ) && is_dir( $path ) )
        {
            $directories[] = new DirectoryIterator( $path );
        }

        $path = $legacyExtensionPath . '/root_' . $this->environment . '/';
        if ( $this->fileSystem->exists( $path ) && is_dir( $path ) )
        {
            $directories[] = new DirectoryIterator( $path );
        }

        foreach ( $directories as $directory )
        {
            foreach ( $directory as $item )
            {
                if ( $item->isDot() || $item->isLink() )
                {
                    continue;
                }

                if ( $item->isDir() && in_array( $item->getBasename(), $this->legacyDistFolders ) )
                {
                    if ( in_array( $item->getBasename(), $this->blacklistedItems ) )
                    {
                        continue;
                    }

                    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $item->getPathname() ) ) as $subItem )
                    {
                        /** @var \SplFileInfo $subItem */
                        if ( $subItem->isFile() && !$subItem->isLink() )
                        {
                            if ( in_array( $subItem->getBasename(), $this->blacklistedItems ) )
                            {
                                continue;
                            }

                            // Allow filename to have .patched at the end of string (dehctap. in reverse file name)
                            // to work around eZ legacy autoload generator warning about duplicate class names
                            $fileName = $subItem->getBasename();
                            if ( strpos( strrev( $fileName ), 'dehctap.' ) === 0 )
                            {
                                $fileName = str_replace( '.patched', '', $fileName );
                            }

                            $filePath = $this->fileSystem->makePathRelative(
                                realpath( $subItem->getPath() ),
                                $directory->getPath()
                            ) . $fileName;

                            $filePath = $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' ) . '/' . $filePath;

                            if ( $this->fileSystem->exists( $filePath ) && is_file( $filePath ) && !is_link( $filePath ) )
                            {
                                // If the destination is a real file, we'll just overwrite it, with backup
                                // but only if it differs from the original
                                if ( md5( file_get_contents( $subItem->getPathname() ) ) == md5( file_get_contents( $filePath ) ) )
                                {
                                    continue;
                                }

                                $this->fileSystem->copy( $filePath, $filePath . '.backup.' . date( 'Y-m-d-H-i-s' ), true );
                                $this->fileSystem->copy( $subItem->getPathname(), $filePath, true );
                            }
                            else if ( !$this->fileSystem->exists( $filePath ) || is_link( $filePath ) )
                            {
                                $this->verifyAndSymlinkFile(
                                    $subItem->getPathname(),
                                    $filePath,
                                    $output
                                );
                            }
                        }
                    }
                }
                else if ( $item->isDir() || $item->isFile() )
                {
                    if ( in_array( $item->getBasename(), $this->blacklistedItems ) )
                    {
                        continue;
                    }

                    $destination = $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' ) . '/' . $item->getBasename();

                    if ( $item->isDir() )
                    {
                        $this->verifyAndSymlinkDirectory(
                            $item->getPathname(),
                            $destination,
                            $output
                        );
                    }
                    else
                    {
                        $this->verifyAndSymlinkFile(
                            $item->getPathname(),
                            $destination,
                            $output
                        );
                    }
                }
            }
        }
    }
}
