# TODO

1. ~~Show number of tickets sold instead of number of ticket orders in the Events index~~ Done — tickets_sold is now computed live from ticket records via Event::getTicketsSold()
2. ~~When you create a reservation with full credit coverage, it still shows "Pay online" and "Pay with cash" instead of "reserve"~~ Done — ConfirmReservationAction now checks cost and shows "Reserve" button when credits fully cover the reservation. Also removed entire Charge payment system (PaymentService, PricingService, Charge model, ChargeStatus enum, old payment actions, BackfillChargesToOrders command) and migrated all references to Finance Order/Transaction system.
3. When a reservation is cancelled before its start time, its order should be refunded
4. Staff dashboard revenue numbers are off by about two factors of ten
5. The "free hours used" box on the member's practice space reservation area shouldn't include refunded orders in its reckoning for hours used.
6. Update "Upcoming" reservation tab to show all future reservations without a cutoff
