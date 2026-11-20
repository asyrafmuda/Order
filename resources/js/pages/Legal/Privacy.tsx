import { usePage } from '@inertiajs/react';
import { LegalPage, LegalSection } from '@/components/storefront/LegalPage';

export default function Privacy() {
    const { shop } = usePage().props;

    return (
        <LegalPage
            title="Privacy Notice"
            updated="November 2026"
            intro={
                <>
                    This notice explains what personal data {shop.name} collects when you order through this website, why we collect it and what you can do about it. It is issued under the
                    Personal Data Protection Act 2010 (PDPA) of Malaysia.
                </>
            }
        >
            <LegalSection title="1. What we collect">
                <ul>
                    <li>
                        <strong>Order details:</strong> your name, mobile number, the items you order, notes for the kitchen, and the time and value of the order.
                    </li>
                    <li>
                        <strong>Delivery details:</strong> recipient name, mobile number, address and any note for the rider, when you choose delivery.
                    </li>
                    <li>
                        <strong>Account details:</strong> email address and an encrypted password, if you create an account, plus any addresses you choose to save.
                    </li>
                    <li>
                        <strong>Payment status:</strong> the payment method, amount and the gateway’s transaction reference. We do not receive or store card numbers or online-banking credentials.
                    </li>
                    <li>
                        <strong>Technical data:</strong> a session cookie that keeps your ticket and sign-in, and standard server logs.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection title="2. Why we use it">
                <ul>
                    <li>To prepare, hand over or deliver your order and to tell you its status.</li>
                    <li>To take payment, issue receipts and process refunds.</li>
                    <li>To keep sales and tax records required by law.</li>
                    <li>To prevent fraudulent or abusive orders and keep the service secure.</li>
                </ul>
                <p>We do not sell your data and we do not send marketing messages unless you have asked for them.</p>
            </LegalSection>

            <LegalSection title="3. Who we share it with">
                <ul>
                    <li>
                        <strong>Our crew and riders</strong> see what they need to prepare and deliver your order.
                    </li>
                    <li>
                        <strong>Payment gateway</strong> — to process your online payment.
                    </li>
                    <li>
                        <strong>Point-of-sale provider (Loyverse)</strong> — a receipt for each confirmed order, which may include your name and mobile number as a reference.
                    </li>
                    <li>
                        <strong>Messaging provider (WhatsApp)</strong> — your mobile number and the order update we send you.
                    </li>
                    <li>
                        <strong>Hosting provider</strong> — stores the data on our behalf.
                    </li>
                    <li>Authorities, where the law requires it.</li>
                </ul>
                <p>Some of these providers process data outside Malaysia.</p>
            </LegalSection>

            <LegalSection title="4. Order links">
                <p>Each order has a private link to its digital ticket. Anyone you share that link with can see the items and status of the order; your mobile number and address are hidden unless they are viewing from your account or the device that placed the order.</p>
            </LegalSection>

            <LegalSection title="5. How long we keep it">
                <p>Order and payment records are kept for seven years to meet tax and accounting requirements. Account details and saved addresses are kept until you ask us to delete your account.</p>
            </LegalSection>

            <LegalSection title="6. Your choices and rights">
                <ul>
                    <li>You can order as a guest without creating an account.</li>
                    <li>You can view and correct your name, email, mobile number and saved addresses under “Profile &amp; addresses”.</li>
                    <li>You may ask us for a copy of your personal data, to correct it, or to stop using it and delete your account. Records we are legally required to keep are retained.</li>
                </ul>
                <p>Providing your name and mobile number is necessary to place an order; without them we cannot accept it.</p>
            </LegalSection>

            <LegalSection title="7. Security">
                <p>Passwords are stored hashed, access to order data is limited to staff who need it, and connections to this site are encrypted. No system is perfectly secure, so please use a password you do not use elsewhere.</p>
            </LegalSection>

            <LegalSection title="8. Changes">
                <p>We will update this notice when our practices change and show the new date at the top of the page.</p>
            </LegalSection>
        </LegalPage>
    );
}
