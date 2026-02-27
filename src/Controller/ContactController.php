<?php

namespace App\Controller;

use App\Dto\ContactRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ContactController extends AbstractController
{
    #[Route('/api/contact', name: 'app_contact', methods: ['POST'])]
    public function send(Request $request, MailerInterface $mailer, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $dto = new ContactRequest();
        $dto->firstName = $data['firstName'];
        $dto->lastName = $data['lastName'];
        $dto->email = $data['email'];
        $dto->subject = $data['subject'];
        $dto->message = $data['message'];

        $errors = $validator->validate($dto);
        if(count($errors) > 0){
            return $this->json(['message' => 'Données invalides'], 400);
        }

        $subjectLabels = [
            'question' => 'Question sur le jeu concours',
            'bug' => 'Signalement d\'un bug',
            'help' => 'Demande d\'aide',
            'demo' => 'Demande de démo'
        ];

        $email = (new Email())
            ->from($dto->email)
            ->to('contact@jonathanlore.fr')
            ->replyTo($dto->email)
            ->subject('[Thé Tip Top] ' . ($subjectLabels[$dto->subject] ?? $dto->subject))
            ->text(sprintf(
                "De : %s %s <%s>\n\n%s",
                $dto->firstName,
                $dto->lastName,
                $dto->email,
                $dto->message
            ));

        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true], 200);
    }
}
