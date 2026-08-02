<div class="bg-white">    

    <p class="text-sm text-gray-500 mb-4">Explanation of User Status</p>

    <div class="overflow-x-auto">
        <table class="w-full border border-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="border border-gray-200 px-4 py-2 text-left font-semibold w-1/4">
                        Status
                    </th>
                    <th class="border border-gray-200 px-4 py-2 text-left font-semibold">
                        Details
                    </th>
                </tr>
            </thead>

            <tbody>

                <!-- ACTIVE -->
                <tr>
                    <td class="border border-gray-200 px-4 py-3 font-medium">
                        ACTIVE
                    </td>
                    <td class="border border-gray-200 px-4 py-3">
                        All user has completed registration and OTP verification.
                    </td>
                </tr>

                <!-- ONBOARDING -->
                <tr>
                    <td class="border border-gray-200 px-4 py-3 font-medium">
                        ONBOARDING
                    </td>
                    <td class="border border-gray-200 px-4 py-3">
                        <p>Applicable to Rider and Merchant only.</p>
                        <p>
                            Rider or Merchant has completed registration and OTP
                            verification but is still in the onboarding process.
                        </p>
                    </td>
                </tr>

                <!-- PENDING -->
                <tr>
                    <td class="border border-gray-200 px-4 py-3 font-medium">
                        PENDING
                    </td>
                    <td class="border border-gray-200 px-4 py-3">
                        Any user who has registered but has not completed OTP verification.
                    </td>
                </tr>

                <!-- INACTIVE -->
                <tr>
                    <td class="border border-gray-200 px-4 py-3 font-medium">
                        INACTIVE
                    </td>
                    <td class="border border-gray-200 px-4 py-3">
                        Any user who has been deactivated or suspended by the admin.
                        User cannot login to the Mobile Apps.
                    </td>
                </tr>

                <!-- REJECTED -->
                <tr>
                    <td class="border border-gray-200 px-4 py-3 font-medium">
                        REJECTED
                    </td>
                    <td class="border border-gray-200 px-4 py-3">
                        Applicable to Rider and Merchant only.
                        Users whose application has been rejected by admin.
                    </td>
                </tr>

            </tbody>
        </table>
    </div>
</div>
