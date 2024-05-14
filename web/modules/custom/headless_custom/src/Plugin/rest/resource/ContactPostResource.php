<?php

namespace Drupal\headless_custom\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Component\Serialization\Json;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\contact\MailHandlerInterface;
use Drupal\contact\Entity\Message;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\UserData;

/**
 * Provides a custom endpoint which can be used to submit personal contact forms.
 * @RestResource(
 *   id = "contact_post",
 *   label = @Translation("Custom resource to submit personal contact form."),
 *   uri_paths = {
 *     "create" = "/api/contact-user"
 *   }
 * )
 */
class ContactPostResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currently authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The contact mail handler service.
   *
   * @var \Drupal\contact\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a ContactPostResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The currently authenticated user.
   * @param \Drupal\contact\MailHandlerInterface $mail_handler
   *   The contact mail handler service.
   * @param \Drupal\user\UserData $user_data
   *   The user data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, MailHandlerInterface $mail_handler, UserData $user_data)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->mailHandler = $mail_handler;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('contact.mail_handler'),
      $container->get('user.data'),
    );
  }

  /**
   * Post method for the contact post endpoint.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *  Represents an HTTP request.
   *
   * @return ResourceResponse
   *
   * @throws \Symfony\Component\HttpFoundation\Response
   *  When invalid data passed or server error.
   */
  public function post(Request $request) {
    $contact_form_data = Json::decode($request->getContent());

    //Check if any of the required fields is missing.
    if (empty($contact_form_data['recipient']) || empty($contact_form_data['subject']) || empty($contact_form_data['message'])) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Recipient,subject or message is missing.');
    }

    // Check if Recipient exists or not.
    if (!$this->entityTypeManager->getStorage('user')->load($contact_form_data['recipient'])) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Recipient user does not exist. Please provide a valid user id of the recipient.');
    }

    // Check if the requested user has disabled their contact form.
    $user_data = $this->userData->get('contact', $contact_form_data['recipient'], 'enabled');
    if (isset($user_data) && !$user_data) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, "This recipient has disabled it's contact form and so cannot be contacted for now.");
    }

    // Create the message entity using the contact form data.
    $message = Message::create([
      'contact_form' => 'personal',
      'name' => $this->currentUser->getAccountName(),
      'mail' => $this->currentUser->getEmail(),
      'recipient' => $contact_form_data['recipient'],
      'subject' => $contact_form_data['subject'],
      'message' => $contact_form_data['message'],
      'copy' =>  isset($contact_form_data['copy']) ? 1 : 0,
    ]);
    $message->save();

    try {
      $this->mailHandler->sendMailMessages($message, $this->currentUser);
    }
    catch (\Exception $e) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to send email, please try again later.');
    }

    return new ResourceResponse(['message' => 'Your message has been sent.']);
  }
}
