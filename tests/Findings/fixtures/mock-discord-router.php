<?php
    /**
     * Mock Discord webhook: appends every request body (one JSON per line) to
     * FINDINGS_DISCORD_FILE and answers FINDINGS_DISCORD_STATUS (default 204).
     */
    file_put_contents(getenv('FINDINGS_DISCORD_FILE'), file_get_contents('php://input')."\n", FILE_APPEND | LOCK_EX);
    http_response_code((int)(getenv('FINDINGS_DISCORD_STATUS') ?: 204));
