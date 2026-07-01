<?php

namespace App\Message;

/** Marker for messages that mutate state; routed to Kafka and applied by the worker. */
interface Command
{
}
