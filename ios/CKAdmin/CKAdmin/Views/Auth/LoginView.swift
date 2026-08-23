import SwiftUI

/// Login screen with email/password fields and error display.
///
/// Uses `@Environment(AuthManager.self)` to access the shared auth manager
/// and trigger navigation to the main app on successful login.
struct LoginView: View {
    @Environment(AuthManager.self) private var authManager

    @State private var email = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var errorMessage: String?

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 32) {
                    // App branding
                    VStack(spacing: 12) {
                        Image("Logo")
                            .resizable()
                            .scaledToFit()
                            .frame(height: 50)
                            .accessibilityHidden(true)

                        Text("CK Enterprises UK")
                            .font(.title3)
                            .fontWeight(.medium)
                            .foregroundStyle(.secondary)
                    }
                    .padding(.top, 40)

                    // Login form
                    VStack(spacing: 16) {
                        TextField("Email", text: $email)
                            .keyboardType(.emailAddress)
                            .autocorrectionDisabled()
                            .textInputAutocapitalization(.never)
                            .textContentType(.emailAddress)
                            .padding()
                            .background(Color(.systemGray6))
                            .clipShape(RoundedRectangle(cornerRadius: 10))
                            .accessibilityLabel("Email address")

                        SecureField("Password", text: $password)
                            .textContentType(.password)
                            .padding()
                            .background(Color(.systemGray6))
                            .clipShape(RoundedRectangle(cornerRadius: 10))
                            .accessibilityLabel("Password")
                    }
                    .padding(.horizontal)

                    // Error message
                    if let errorMessage {
                        Text(errorMessage)
                            .font(.callout)
                            .foregroundStyle(.red)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal)
                            .accessibilityLabel("Error: \(errorMessage)")
                    }

                    // Login button
                    Button {
                        performLogin()
                    } label: {
                        Group {
                            if isLoading {
                                ProgressView()
                                    .tint(.white)
                            } else {
                                Text("Sign In")
                                    .fontWeight(.semibold)
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .padding()
                        .background(loginButtonDisabled ? Color.blue.opacity(0.5) : Color.blue)
                        .foregroundStyle(.white)
                        .clipShape(RoundedRectangle(cornerRadius: 10))
                    }
                    .disabled(loginButtonDisabled)
                    .padding(.horizontal)
                    .accessibilityLabel("Sign In")
                    .accessibilityHint("Double tap to sign in with your email and password")
                }
                .padding()
            }
            .navigationTitle("Sign In")
            .navigationBarTitleDisplayMode(.inline)
        }
    }

    // MARK: - Private

    private var loginButtonDisabled: Bool {
        email.isEmpty || password.isEmpty || isLoading
    }

    private func performLogin() {
        errorMessage = nil
        isLoading = true

        Task {
            do {
                try await authManager.login(email: email, password: password)
            } catch let error as AuthError {
                errorMessage = error.errorDescription
            } catch {
                errorMessage = "An unexpected error occurred."
            }
            isLoading = false
        }
    }
}

#Preview {
    LoginView()
        .environment(AuthManager())
}
