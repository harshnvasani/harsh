<?php

namespace Webex\Registration\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Area;
use Webex\Registration\Model\ConfigInterface;

class CustomerRegisterSuccess implements ObserverInterface
{
    
    protected $_customerFactory;
    protected $customerRepository;
    protected $_logger;
    protected $inlineTranslation;
    protected $escaper;
    protected $transportBuilder;
    private $storeManager;
    private $contactsConfig;

    public function __construct(
        StateInterface $inlineTranslation,
        Escaper $escaper,
        TransportBuilder $transportBuilder,
        ConfigInterface $contactsConfig,
        CustomerFactory $customerFactory,
        \Webex\Registration\Logger\Logger $logger,
        StoreManagerInterface $storeManager = null
    ) {
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->transportBuilder = $transportBuilder;
        $this->contactsConfig = $contactsConfig;
        $this->storeManager = $storeManager ?: ObjectManager::getInstance()->get(StoreManagerInterface::class);
        $this->_customerFactory = $customerFactory;
        $this->_logger = $logger;
    }

    /**
     * Manages redirect
     */
    public function execute(Observer $observer)
    {

        $accountController = $observer->getAccountController();
        $customer = $observer->getCustomer();
        $request = $accountController->getRequest();
        $firstname = $request->getParam('firstname');
        $firstname = preg_replace('/\s+/', '', $firstname);
        $lastname=$request->getParam('lastname');
        $email = $request->getParam('email');
        $customer = $this->_customerFactory->create()->load($customer->getId());
        $customer->setData('firstname', $firstname);
        $customer->save();
        $this->_logger->info('FirstName : '. $firstname . PHP_EOL. ' LastName : '. $lastname . PHP_EOL. ' Email  :'. $email);
        $vars = array('firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email
            );
        return $this->sendEmail(['data' => $vars]);
        
    }

    public function sendEmail($templateVars = [])
    {
        try {
            $this->inlineTranslation->suspend();
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->contactsConfig->emailTemplate())
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $this->storeManager->getStore()->getId()
                    ]
                )
                ->setTemplateVars($templateVars)
                ->setFrom($this->contactsConfig->emailSender())
                ->addTo($this->contactsConfig->emailRecipient())
                ->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->_logger->debug($e->getMessage());
        }
    }
}