<?php

namespace App\Ai;

/**
 * The one seam between RSMS and any AI vendor. Domain and UI code never see
 * an implementation of this directly -- they call an assistant class
 * (CommunicationAssistant), which calls AiGenerationService, which calls
 * this. Swapping vendors means writing one new implementation, not touching
 * any assistant or domain code.
 */
interface AiProvider
{
    public function generate(AiGenerationRequest $request): AiGenerationResult;
}
