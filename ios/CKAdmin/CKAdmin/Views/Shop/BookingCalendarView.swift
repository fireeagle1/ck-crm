import SwiftUI

/// Monthly calendar view showing booking blocks by product.
struct BookingCalendarView: View {
    @State private var calendarData: CalendarData?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var currentYear: Int = Calendar.current.component(.year, from: Date())
    @State private var currentMonth: Int = Calendar.current.component(.month, from: Date())

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading && calendarData == nil {
                ProgressView("Loading calendar...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let errorMessage, calendarData == nil {
                errorView(message: errorMessage)
            } else if let data = calendarData {
                calendarContent(data)
            }
        }
        .navigationTitle("Booking Calendar")
        .navigationBarTitleDisplayMode(.inline)
        .task { await loadCalendar() }
    }

    // MARK: - Content

    private func calendarContent(_ data: CalendarData) -> some View {
        ScrollView {
            VStack(spacing: 16) {
                // Month navigation
                HStack {
                    Button { navigateMonth(-1) } label: {
                        Image(systemName: "chevron.left")
                            .font(.title3)
                            .fontWeight(.semibold)
                    }

                    Spacer()

                    Text(monthYearLabel)
                        .font(.title3)
                        .fontWeight(.bold)

                    Spacer()

                    Button { navigateMonth(1) } label: {
                        Image(systemName: "chevron.right")
                            .font(.title3)
                            .fontWeight(.semibold)
                    }
                }
                .padding(.horizontal)

                // Bookings list grouped by product
                let grouped = Dictionary(grouping: data.bookings, by: { $0.productName ?? "Unknown" })

                if grouped.isEmpty {
                    ContentUnavailableView(
                        "No Bookings",
                        systemImage: "calendar.badge.clock",
                        description: Text("No bookings for this month.")
                    )
                    .padding(.top, 40)
                } else {
                    ForEach(grouped.keys.sorted(), id: \.self) { productName in
                        if let bookings = grouped[productName] {
                            productSection(name: productName, bookings: bookings)
                        }
                    }
                }
            }
            .padding()
        }
        .refreshable { await loadCalendar() }
    }

    // MARK: - Product Section

    private func productSection(name: String, bookings: [CalendarBooking]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(name)
                .font(.headline)
                .padding(.bottom, 2)

            ForEach(bookings) { booking in
                HStack(spacing: 10) {
                    // Color bar
                    RoundedRectangle(cornerRadius: 2)
                        .fill(stageColor(booking.fulfilmentStage ?? "ordered"))
                        .frame(width: 4)

                    VStack(alignment: .leading, spacing: 2) {
                        Text(booking.customerName ?? "Unknown Customer")
                            .font(.subheadline)
                            .fontWeight(.medium)

                        HStack {
                            Text("\(formatDate(booking.startDate)) – \(formatDate(booking.endDate))")
                                .font(.caption)
                                .foregroundStyle(.secondary)

                            if booking.quantity > 1 {
                                Text("×\(booking.quantity)")
                                    .font(.caption)
                                    .foregroundStyle(.secondary)
                            }
                        }
                    }

                    Spacer()

                    Text((booking.fulfilmentStage ?? booking.status).replacingOccurrences(of: "_", with: " ").capitalized)
                        .font(.caption2)
                        .fontWeight(.medium)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(stageColor(booking.fulfilmentStage ?? "ordered").opacity(0.15))
                        .foregroundStyle(stageColor(booking.fulfilmentStage ?? "ordered"))
                        .clipShape(Capsule())
                }
                .padding(.vertical, 6)
                .padding(.horizontal, 10)
                .background(Color(.systemGray6))
                .clipShape(RoundedRectangle(cornerRadius: 8))
            }
        }
        .padding()
        .background(Color(.systemBackground))
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .shadow(color: .black.opacity(0.05), radius: 2, y: 1)
    }

    // MARK: - Navigation

    private func navigateMonth(_ offset: Int) {
        var month = currentMonth + offset
        var year = currentYear

        if month > 12 { month = 1; year += 1 }
        if month < 1 { month = 12; year -= 1 }

        currentMonth = month
        currentYear = year
        Task { await loadCalendar() }
    }

    private var monthYearLabel: String {
        let components = DateComponents(year: currentYear, month: currentMonth)
        guard let date = Calendar.current.date(from: components) else { return "" }
        let formatter = DateFormatter()
        formatter.dateFormat = "MMMM yyyy"
        return formatter.string(from: date)
    }

    // MARK: - API

    private func loadCalendar() async {
        isLoading = true
        errorMessage = nil
        do {
            let endpoint = Endpoint(
                path: "/admin/shop/rentals/calendar",
                queryItems: ["year": String(currentYear), "month": String(currentMonth)]
            )
            let response: BookingCalendarResponse = try await apiClient.request(endpoint)
            calendarData = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "Failed to load calendar."
        }
        isLoading = false
    }

    // MARK: - Helpers

    private func stageColor(_ stage: String) -> Color {
        switch stage {
        case "ordered": return .blue
        case "packing": return .orange
        case "ready": return .purple
        case "checked_out": return .green
        case "returned": return .mint
        case "inspected": return .gray
        case "active": return .blue
        case "confirmed": return .green
        default: return .gray
        }
    }

    private func formatDate(_ dateString: String) -> String {
        let inputFormatter = DateFormatter()
        inputFormatter.dateFormat = "yyyy-MM-dd"
        inputFormatter.locale = Locale(identifier: "en_US_POSIX")
        guard let date = inputFormatter.date(from: dateString) else { return dateString }
        let outputFormatter = DateFormatter()
        outputFormatter.dateFormat = "d MMM"
        return outputFormatter.string(from: date)
    }

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(.orange)
            Text("Unable to Load Calendar").font(.headline)
            Text(message).font(.subheadline).foregroundStyle(.secondary)
            Button { Task { await loadCalendar() } } label: {
                Label("Retry", systemImage: "arrow.clockwise").fontWeight(.medium)
            }.buttonStyle(.borderedProminent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
