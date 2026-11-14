<?php

declare(strict_types=1);

// addPhoneNumber() is registered by installing the package (composer autoload "files"), so that
// no project has to call register() in its bootstrap. Calling it again is harmless.
ADT\Forms\Controls\PhoneNumberInput::register();
