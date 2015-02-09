<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Mtf;

use Magento\Mtf\ObjectManager\Factory;
use Magento\Mtf\Stdlib\BooleanUtils;
use Magento\Mtf\ObjectManager as MagentoObjectManager;

/**
 * Class ObjectManagerFactory
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ObjectManagerFactory
{
    /**
     * Object Manager class name
     *
     * @var string
     */
    protected $locatorClassName = '\Magento\Mtf\ObjectManager';

    /**
     * DI Config class name
     *
     * @var string
     */
    protected $configClassName = '\Magento\Mtf\ObjectManager\Config';

    /**
     * Create Object Manager
     *
     * @param array $sharedInstances
     * @return ObjectManager
     */
    public function create(array $sharedInstances = [])
    {
        if (!defined('MTF_BP')) {
            $basePath = str_replace('\\', '/', dirname(__DIR__));
            define('MTF_BP', $basePath);
        }
        if (!defined('MTF_TESTS_PATH')) {
            define('MTF_TESTS_PATH', MTF_BP . '/tests/app/');
        }
        if (!defined('MTF_STATES_PATH')) {
            define('MTF_STATES_PATH', MTF_BP . '/Magento/Mtf/App/State/');
        }

        /** @var \Magento\Mtf\ObjectManager\Config $diConfig */
        $diConfig = new $this->configClassName();

        $factory = new Factory($diConfig);
        $argInterpreter = $this->createArgumentInterpreter(new BooleanUtils());
        $argumentMapper = new \Magento\Mtf\ObjectManager\Config\Mapper\Dom($argInterpreter);

        $sharedInstances['Magento\Mtf\Data\Argument\InterpreterInterface'] = $argInterpreter;
        $sharedInstances['Magento\Mtf\ObjectManager\Config\Mapper\Dom'] = $argumentMapper;
        /** @var \Magento\Mtf\ObjectManager $objectManager */
        $objectManager = new $this->locatorClassName($factory, $diConfig, $sharedInstances);

        $factory->setObjectManager($objectManager);
        ObjectManager::setInstance($objectManager);

        self::configure($objectManager);

        return $objectManager;
    }

    /**
     * Return newly created instance on an argument interpreter, suitable for processing DI arguments
     *
     * @param \Magento\Mtf\Stdlib\BooleanUtils $booleanUtils
     * @return \Magento\Mtf\Data\Argument\InterpreterInterface
     */
    protected function createArgumentInterpreter(
        \Magento\Mtf\Stdlib\BooleanUtils $booleanUtils
    ) {
        $constInterpreter = new \Magento\Mtf\Data\Argument\Interpreter\Constant();
        $result = new \Magento\Mtf\Data\Argument\Interpreter\Composite(
            array(
                'boolean' => new \Magento\Mtf\Data\Argument\Interpreter\Boolean($booleanUtils),
                'string' => new \Magento\Mtf\Data\Argument\Interpreter\String($booleanUtils),
                'number' => new \Magento\Mtf\Data\Argument\Interpreter\Number(),
                'null' => new \Magento\Mtf\Data\Argument\Interpreter\NullType(),
                'const' => $constInterpreter,
                'object' => new \Magento\Mtf\Data\Argument\Interpreter\Object($booleanUtils),
                'init_parameter' => new \Magento\Mtf\Data\Argument\Interpreter\Argument($constInterpreter),
            ),
            \Magento\Mtf\ObjectManager\Config\Reader\Dom::TYPE_ATTRIBUTE
        );
        // Add interpreters that reference the composite
        $result->addInterpreter('array', new \Magento\Mtf\Data\Argument\Interpreter\ArrayType($result));
        return $result;
    }

    /**
     * Get MTF Object Manager instance
     *
     * @return ObjectManager
     */
    public static function getObjectManager()
    {
        if (!$objectManager = ObjectManager::getInstance()) {
            $objectManagerFactory = new self();
            $objectManager = $objectManagerFactory->create();
        }

        return $objectManager;
    }

    /**
     * Configure Object Manager
     * This method is static to have the ability to configure multiple instances of Object manager when needed
     *
     * @param \Magento\Mtf\ObjectManagerInterface $objectManager
     * @return void
     */
    public static function configure(\Magento\Mtf\ObjectManagerInterface $objectManager)
    {
        $objectManager->configure(
            $objectManager->get('Magento\Mtf\ObjectManager\ConfigLoader\Primary')->load()
        );

        $objectManager->configure(
            $objectManager->get('Magento\Mtf\ObjectManager\ConfigLoader\Module')->load()
        );

        $objectManager->configure(
            $objectManager->get('Magento\Mtf\ObjectManager\ConfigLoader\Module')->load('etc/ui')
        );

        $objectManager->configure(
            $objectManager->get('Magento\Mtf\ObjectManager\ConfigLoader\Module')->load('etc/curl')
        );
    }
}