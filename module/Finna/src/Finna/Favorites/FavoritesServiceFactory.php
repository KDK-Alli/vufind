<?php
/**
 * Favorites service factory
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Favorites
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Favorites;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FavoritesServiceFactory extends \VuFind\Favorites\FavoritesServiceFactory
{
    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name (unused)
     * @param array              $options Extra options (unused)
     *
     * @return FavoritesService
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $tableManager = $sm->get(\VuFind\Db\Table\PluginManager::class);
        return new FavoritesService(
            $tableManager->get('userlist'),
            $tableManager->get('resource'),
            $sm->get(\VuFind\Record\Cache::class)
        );
    }
}
