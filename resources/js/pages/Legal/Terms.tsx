import { Link, usePage } from '@inertiajs/react';
import { LegalPage, LegalSection } from '@/components/storefront/LegalPage';

export default function Terms() {
    const { shop } = usePage().props;

    return (
        <LegalPage
            title="Terms of Service"
            updated="November 2026"
            intro={
                <>
                    These terms apply when you order from {shop.name} through this website. By placing an order you agree to them. Please read them together with our{' '}
                    <Link href={route('legal.privacy')} className="font-semibold text-flame-400 hover:underline">
                        Privacy Notice
                    </Link>
                    .
                </>
            }
        >
            <LegalSection title="1. Ordering">
                <p>An order is accepted once you see a queue token (for example A012) and your digital ticket. Menu items, prices and availability can change without notice, and an item may sell out after you add it to your ticket.</p>
                <p>Please make sure your name and mobile number are correct: we use them to tell you when your order is ready.</p>
            </LegalSection>

            <LegalSection title="2. Prices and payment">
                <ul>
                    <li>Prices are in Ringgit Malaysia (RM) and include SST unless the checkout says otherwise.</li>
                    <li>Online payments are processed by a licensed third-party payment gateway. We never see or store your card or banking details.</li>
                    <li>An online order is sent to the kitchen only after payment is confirmed. Unpaid online orders are cancelled automatically after 30 minutes.</li>
                    <li>Where “Cash at Counter” is offered, payment is due when you collect your order.</li>
                </ul>
            </LegalSection>

            <LegalSection title="3. Pickup">
                <p>The ready time on your ticket is an estimate based on how busy the kitchen is. Show your QR code or pickup code at the counter to collect.</p>
                <p>Food is best eaten fresh. Orders not collected within a reasonable time after they are ready may be disposed of for food-safety reasons; unpaid orders that are not collected are cancelled.</p>
            </LegalSection>

            <LegalSection title="4. Delivery">
                <ul>
                    <li>Delivery is available only in the areas listed at checkout, and each area has its own fee and minimum order.</li>
                    <li>Delivery times are estimates and can be affected by weather, traffic and order volume.</li>
                    <li>Please be reachable on the mobile number you provided. If our rider cannot reach you at the address given, the order may be returned to the shop and is not refundable.</li>
                </ul>
            </LegalSection>

            <LegalSection title="5. Cancellations and refunds">
                <ul>
                    <li>You can cancel an online order yourself any time before you pay.</li>
                    <li>Once the kitchen has started preparing a paid order it cannot be cancelled by the customer. Contact us straight away if something is wrong and we will do our best to help.</li>
                    <li>If we have to cancel an order (for example an item ran out), anything you paid is refunded in full to the original payment method. Banks and e-wallets usually take 3–10 working days to show the refund.</li>
                    <li>If your order is wrong or not up to standard, tell us on the day with your queue token so we can replace or refund the affected item.</li>
                </ul>
            </LegalSection>

            <LegalSection title="6. Allergens and special requests">
                <p>Our kitchen handles egg, milk, wheat, soy, sesame, seafood and nuts, and we cannot guarantee that any item is free from traces of them. We follow notes such as “no sauce” as closely as we can, but if you have a serious allergy please speak to our crew before ordering.</p>
            </LegalSection>

            <LegalSection title="7. Your account">
                <p>You are responsible for keeping your password private and for orders placed through your account. We may suspend accounts used for fraudulent or abusive orders.</p>
            </LegalSection>

            <LegalSection title="8. Liability">
                <p>Nothing in these terms limits rights you have under Malaysian consumer protection law. Beyond those rights, our liability for any order is limited to the amount you paid for it.</p>
            </LegalSection>

            <LegalSection title="9. Changes and governing law">
                <p>We may update these terms from time to time; the version shown here at the time of your order applies to that order. These terms are governed by the laws of Malaysia.</p>
            </LegalSection>
        </LegalPage>
    );
}
