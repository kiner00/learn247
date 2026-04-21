<?php

namespace App\Actions\Account;

use App\Jobs\VerifyKycDocuments;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SubmitKyc
{
    public function __construct(private StorageService $storage) {}

    public function execute(User $user, UploadedFile $idDocument, UploadedFile $selfie): User
    {
        if ($user->kyc_status === User::KYC_SUBMITTED) {
            throw ValidationException::withMessages(['kyc' => 'Your KYC is already under review.']);
        }

        if ($user->kyc_status === User::KYC_APPROVED) {
            throw ValidationException::withMessages(['kyc' => 'Your KYC is already approved.']);
        }

        $idUrl = $this->storage->upload($idDocument, 'kyc-documents');
        $selfieUrl = $this->storage->upload($selfie, 'kyc-documents');

        $user->update([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_id_document' => $idUrl,
            'kyc_selfie' => $selfieUrl,
            'kyc_submitted_at' => now(),
            'kyc_rejected_reason' => null,
        ]);

        VerifyKycDocuments::dispatch($user);

        return $user->refresh();
    }
}
